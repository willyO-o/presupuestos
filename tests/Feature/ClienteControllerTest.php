<?php

use App\Models\Cliente;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    // Los permisos son la fuente de verdad de config/acl.php; se crean aquí
    // en vez de correr el seeder completo (que además crea usuarios de
    // prueba) para mantener el test rápido y aislado.
    collect(['clientes.ver', 'clientes.crear', 'clientes.editar', 'clientes.eliminar'])
        ->each(fn (string $permission) => Permission::findOrCreate($permission, 'web'));
});

function userWithClientePermissions(string ...$permissions): User
{
    $user = User::factory()->create();
    $user->givePermissionTo($permissions);

    return $user;
}

test('guests are redirected to login', function () {
    $this->get(route('clientes.index'))->assertRedirect(route('login'));
});

test('a user without permission cannot see the list', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('clientes.index'))
        ->assertForbidden();
});

test('a user with permission sees the paginated list', function () {
    Cliente::factory()->count(3)->create();
    $user = userWithClientePermissions('clientes.ver');

    $response = $this->actingAs($user)->get(route('clientes.index'));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('Clientes/Index')
        ->has('clientes.data', 3)
    );
});

test('the list can be searched by razon_social, nit or contacto', function () {
    Cliente::factory()->create(['razon_social' => 'Compañía de Alimentos Ltda.', 'nit' => '123456']);
    Cliente::factory()->create(['razon_social' => 'Importadora Norte', 'nit' => '987654']);
    $user = userWithClientePermissions('clientes.ver');

    $response = $this->actingAs($user)->get(route('clientes.index', ['search' => 'Alimentos']));

    $response->assertInertia(fn ($page) => $page
        ->has('clientes.data', 1)
        ->where('clientes.data.0.razon_social', 'Compañía de Alimentos Ltda.')
    );
});

test('the list can be filtered by estado', function () {
    Cliente::factory()->create(['estado' => 'ACTIVO']);
    Cliente::factory()->inactivo()->create();
    $user = userWithClientePermissions('clientes.ver');

    $response = $this->actingAs($user)->get(route('clientes.index', ['estado' => 'INACTIVO']));

    $response->assertInertia(fn ($page) => $page
        ->has('clientes.data', 1)
        ->where('clientes.data.0.estado', 'INACTIVO')
    );
});

test('a user with permission can create a cliente', function () {
    $user = userWithClientePermissions('clientes.ver', 'clientes.crear');

    $response = $this->actingAs($user)->post(route('clientes.store'), [
        'tipo' => 'JURIDICO',
        'razon_social' => 'Distribuidora Central',
        'nit' => '123456',
        'contacto_nombre' => 'Juan Pérez',
        'telefono' => '71234567',
        'email' => 'contacto@distribuidoracentral.com',
        'direccion' => 'Av. Siempre Viva 123',
        'ciudad' => 'Santa Cruz',
        'estado' => 'ACTIVO',
    ]);

    $response->assertRedirect(route('clientes.index'));
    $response->assertSessionHas('success');
    $this->assertDatabaseHas('cliente', ['razon_social' => 'Distribuidora Central', 'nit' => '123456']);
});

test('a cliente can be created without optional fields', function () {
    $user = userWithClientePermissions('clientes.ver', 'clientes.crear');

    $response = $this->actingAs($user)->post(route('clientes.store'), [
        'tipo' => 'NATURAL',
        'razon_social' => 'Cliente Mínimo',
        'nit' => '999999',
        'contacto_nombre' => '',
        'telefono' => '',
        'email' => '',
        'direccion' => '',
        'ciudad' => '',
        'estado' => 'ACTIVO',
    ]);

    $response->assertRedirect(route('clientes.index'));
    $this->assertDatabaseHas('cliente', ['razon_social' => 'Cliente Mínimo', 'ciudad' => null]);
});

test('a user without permission cannot create a cliente', function () {
    $user = userWithClientePermissions('clientes.ver');

    $this->actingAs($user)->post(route('clientes.store'), [
        'tipo' => 'JURIDICO',
        'razon_social' => 'Distribuidora Central',
        'nit' => '123456',
        'estado' => 'ACTIVO',
    ])->assertForbidden();

    $this->assertDatabaseCount('cliente', 0);
});

test('creating a cliente requires nit to be unique', function () {
    Cliente::factory()->create(['nit' => '123456']);
    $user = userWithClientePermissions('clientes.ver', 'clientes.crear');

    $response = $this->actingAs($user)->post(route('clientes.store'), [
        'tipo' => 'JURIDICO',
        'razon_social' => 'Otra Empresa',
        'nit' => '123456',
        'estado' => 'ACTIVO',
    ]);

    $response->assertSessionHasErrors(['nit']);
    $this->assertDatabaseCount('cliente', 1);
});

test('creating a cliente requires razon_social, nit and a valid estado', function () {
    $user = userWithClientePermissions('clientes.ver', 'clientes.crear');

    $response = $this->actingAs($user)->post(route('clientes.store'), [
        'tipo' => 'JURIDICO',
        'razon_social' => '',
        'nit' => '',
        'estado' => 'DESCONOCIDO',
    ]);

    $response->assertSessionHasErrors(['razon_social', 'nit', 'estado']);
    $this->assertDatabaseCount('cliente', 0);
});

test('a user with permission can update a cliente', function () {
    $cliente = Cliente::factory()->create(['razon_social' => 'Original']);
    $user = userWithClientePermissions('clientes.ver', 'clientes.editar');

    $response = $this->actingAs($user)->put(route('clientes.update', $cliente), [
        'tipo' => $cliente->tipo,
        'razon_social' => 'Actualizado',
        'nit' => $cliente->nit,
        'estado' => 'INACTIVO',
    ]);

    $response->assertRedirect(route('clientes.index'));
    $this->assertDatabaseHas('cliente', [
        'id' => $cliente->id,
        'razon_social' => 'Actualizado',
        'estado' => 'INACTIVO',
    ]);
});

test('updating a cliente keeps its own nit valid', function () {
    $cliente = Cliente::factory()->create(['nit' => '111111']);
    $user = userWithClientePermissions('clientes.ver', 'clientes.editar');

    $response = $this->actingAs($user)->put(route('clientes.update', $cliente), [
        'tipo' => $cliente->tipo,
        'razon_social' => $cliente->razon_social,
        'nit' => '111111',
        'estado' => 'ACTIVO',
    ]);

    $response->assertRedirect(route('clientes.index'));
    $response->assertSessionDoesntHaveErrors();
});

test('a user without permission cannot update a cliente', function () {
    $cliente = Cliente::factory()->create(['razon_social' => 'Original']);
    $user = userWithClientePermissions('clientes.ver');

    $this->actingAs($user)->put(route('clientes.update', $cliente), [
        'tipo' => 'JURIDICO',
        'razon_social' => 'Actualizado',
        'nit' => $cliente->nit,
        'estado' => 'ACTIVO',
    ])->assertForbidden();

    $this->assertDatabaseHas('cliente', ['id' => $cliente->id, 'razon_social' => 'Original']);
});

test('a user with permission can delete a cliente', function () {
    $cliente = Cliente::factory()->create();
    $user = userWithClientePermissions('clientes.ver', 'clientes.eliminar');

    $response = $this->actingAs($user)->delete(route('clientes.destroy', $cliente));

    $response->assertRedirect(route('clientes.index'));
    $this->assertDatabaseMissing('cliente', ['id' => $cliente->id]);
});

test('a user without permission cannot delete a cliente', function () {
    $cliente = Cliente::factory()->create();
    $user = userWithClientePermissions('clientes.ver');

    $this->actingAs($user)->delete(route('clientes.destroy', $cliente))
        ->assertForbidden();

    $this->assertDatabaseHas('cliente', ['id' => $cliente->id]);
});

test('super-admin bypasses individual permissions', function () {
    Cliente::factory()->create();
    Role::findOrCreate('super-admin', 'web');
    $user = User::factory()->create();
    $user->assignRole('super-admin');

    $this->actingAs($user)
        ->get(route('clientes.index'))
        ->assertOk();
});

/*
|--------------------------------------------------------------------------
| Alta rápida (modal embebido en otro formulario, ver CotizacionForm.vue):
| responde JSON en vez de redirigir, para no navegar fuera de ese formulario.
|--------------------------------------------------------------------------
*/

test('a user with permission can quick-create a cliente and gets it back as json', function () {
    $user = userWithClientePermissions('clientes.crear');

    $response = $this->actingAs($user)->postJson(route('clientes.rapido'), [
        'tipo' => 'JURIDICO',
        'razon_social' => 'Cliente de prueba SRL',
        'nit' => '1234567',
        'estado' => 'ACTIVO',
    ]);

    $response->assertOk()->assertJsonPath('razon_social', 'Cliente de prueba SRL');
    $this->assertDatabaseHas('cliente', ['razon_social' => 'Cliente de prueba SRL', 'nit' => '1234567']);
});

test('a user without permission cannot quick-create a cliente', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->postJson(route('clientes.rapido'), [
        'tipo' => 'JURIDICO',
        'razon_social' => 'Cliente de prueba SRL',
        'nit' => '1234567',
        'estado' => 'ACTIVO',
    ])->assertForbidden();

    $this->assertDatabaseCount('cliente', 0);
});

test('quick-creating a cliente validates the same rules as the full form', function () {
    Cliente::factory()->create(['nit' => '1234567']);
    $user = userWithClientePermissions('clientes.crear');

    $this->actingAs($user)->postJson(route('clientes.rapido'), [
        'tipo' => 'JURIDICO',
        'razon_social' => 'Otro cliente',
        'nit' => '1234567',
        'estado' => 'ACTIVO',
    ])->assertJsonValidationErrors('nit');
});

/*
|--------------------------------------------------------------------------
| Logo (opcional): pasa por ConvierteImagenAJpgService como toda imagen
| subida al sistema, y se muestra en el ranking de clientes del BI.
|--------------------------------------------------------------------------
*/

function datosCliente(array $extra = []): array
{
    return [
        'tipo' => 'JURIDICO',
        'razon_social' => 'Distribuidora Illimani S.R.L.',
        'nit' => '1020304050',
        'estado' => 'ACTIVO',
        ...$extra,
    ];
}

test('el logo se sube convertido a jpg y queda su url publica', function () {
    Storage::fake('public');
    $user = userWithClientePermissions('clientes.ver', 'clientes.crear');

    $this->actingAs($user)
        ->post(route('clientes.store'), datosCliente(['logo' => UploadedFile::fake()->image('logo.png', 400, 200)]))
        ->assertRedirect(route('clientes.index'));

    $cliente = Cliente::where('nit', '1020304050')->firstOrFail();

    expect($cliente->logo)->toStartWith('clientes/')->toEndWith('.jpg')
        ->and($cliente->logo_url)->toContain($cliente->logo);
    Storage::disk('public')->assertExists($cliente->logo);
});

test('el logo es opcional', function () {
    $user = userWithClientePermissions('clientes.ver', 'clientes.crear');

    $this->actingAs($user)->post(route('clientes.store'), datosCliente())->assertRedirect(route('clientes.index'));

    expect(Cliente::where('nit', '1020304050')->value('logo'))->toBeNull();
});

test('un logo nuevo reemplaza al anterior y borra su archivo', function () {
    Storage::fake('public');
    Storage::disk('public')->put('clientes/viejo.jpg', 'contenido');
    $cliente = Cliente::factory()->create(['logo' => 'clientes/viejo.jpg']);
    $user = userWithClientePermissions('clientes.ver', 'clientes.editar');

    // POST + _method, como lo manda la pantalla (un PUT multipart no se parsea).
    $this->actingAs($user)->post(route('clientes.update', $cliente), [
        '_method' => 'put',
        ...datosCliente(['nit' => $cliente->nit, 'logo' => UploadedFile::fake()->image('nuevo.png')]),
    ])->assertRedirect(route('clientes.index'));

    Storage::disk('public')->assertMissing('clientes/viejo.jpg');
    expect($cliente->fresh()->logo)->not->toBe('clientes/viejo.jpg');
});

test('editar sin mandar logo conserva el que tenia', function () {
    $cliente = Cliente::factory()->create(['logo' => 'clientes/actual.jpg']);
    $user = userWithClientePermissions('clientes.ver', 'clientes.editar');

    $this->actingAs($user)->put(route('clientes.update', $cliente), datosCliente(['nit' => $cliente->nit]))
        ->assertRedirect(route('clientes.index'));

    expect($cliente->fresh()->logo)->toBe('clientes/actual.jpg');
});

test('no acepta un svg como logo', function () {
    $user = userWithClientePermissions('clientes.ver', 'clientes.crear');
    $svg = UploadedFile::fake()->createWithContent('logo.svg', '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>');

    $this->actingAs($user)->post(route('clientes.store'), datosCliente(['logo' => $svg]))
        ->assertSessionHasErrors('logo');

    $this->assertDatabaseCount('cliente', 0);
});

test('borrar un cliente borra su logo del disco', function () {
    Storage::fake('public');
    Storage::disk('public')->put('clientes/logo.jpg', 'contenido');
    $cliente = Cliente::factory()->create(['logo' => 'clientes/logo.jpg']);
    $user = userWithClientePermissions('clientes.ver', 'clientes.eliminar');

    $this->actingAs($user)->delete(route('clientes.destroy', $cliente))->assertRedirect(route('clientes.index'));

    Storage::disk('public')->assertMissing('clientes/logo.jpg');
});
