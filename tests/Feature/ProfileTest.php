<?php

use App\Models\Empleado;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

test('profile page is displayed', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->get('/profile');

    $response->assertOk();
});

test('profile page includes the linked empleado data', function () {
    $user = User::factory()->create();
    $empleado = Empleado::factory()->create(['user_id' => $user->id, 'nombres' => 'Juan']);

    $response = $this->actingAs($user)->get('/profile');

    $response->assertInertia(fn ($page) => $page
        ->component('Profile/Edit')
        ->where('empleado.id', $empleado->id)
        ->where('empleado.nombre_completo', $empleado->nombre_completo)
    );
});

test('profile page has no empleado when the account is not linked to one', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get('/profile');

    $response->assertInertia(fn ($page) => $page
        ->component('Profile/Edit')
        ->where('empleado', null)
    );
});

test('profile information can be updated', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->patch('/profile', [
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect('/profile');

    $user->refresh();

    $this->assertSame('Test User', $user->name);
    $this->assertSame('test@example.com', $user->email);
    $this->assertNull($user->email_verified_at);
});

test('email verification status is unchanged when the email address is unchanged', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->patch('/profile', [
            'name' => 'Test User',
            'email' => $user->email,
        ]);

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect('/profile');

    $this->assertNotNull($user->refresh()->email_verified_at);
});

test('profile photo can be uploaded', function () {
    Storage::fake('public');
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->patch('/profile', [
            'name' => $user->name,
            'email' => $user->email,
            'foto' => UploadedFile::fake()->image('avatar.jpg'),
        ]);

    $response->assertSessionHasNoErrors()->assertRedirect('/profile');

    $user->refresh();
    expect($user->foto)->not->toBeNull();
    Storage::disk('public')->assertExists($user->foto);
});

test('uploading a new photo removes the previous one', function () {
    Storage::fake('public');
    $user = User::factory()->create(['foto' => 'avatars/old.jpg']);
    Storage::disk('public')->put('avatars/old.jpg', 'contenido');

    $this->actingAs($user)->patch('/profile', [
        'name' => $user->name,
        'email' => $user->email,
        'foto' => UploadedFile::fake()->image('nuevo.jpg'),
    ]);

    Storage::disk('public')->assertMissing('avatars/old.jpg');
});

test('profile photo must be an image', function () {
    Storage::fake('public');
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->patch('/profile', [
            'name' => $user->name,
            'email' => $user->email,
            'foto' => UploadedFile::fake()->create('document.pdf', 100),
        ]);

    $response->assertSessionHasErrors('foto');
});

test('the account cannot be deleted from the profile: DELETE /profile no longer exists', function () {
    $user = User::factory()->create();

    // 405 (metodo no permitido), no 404: la URI /profile sigue existiendo
    // para GET/PATCH, solo se quito el verbo DELETE.
    $this->actingAs($user)
        ->delete('/profile', ['password' => 'password'])
        ->assertMethodNotAllowed();

    $this->assertNotNull($user->fresh());
});

test('the linked empleado can update their own labor data from the profile', function () {
    $user = User::factory()->create();
    $empleado = Empleado::factory()->create(['user_id' => $user->id]);

    $response = $this->actingAs($user)->patch('/profile/empleado', [
        'nombres' => 'Ana María',
        'paterno' => 'Quispe',
        'materno' => 'Mamani',
        'ci' => '7654321 LP',
        'telefono' => '71234567',
        'fecha_ingreso' => '15-03-2024',
    ]);

    $response->assertSessionHasNoErrors()->assertRedirect('/profile');

    $empleado->refresh();
    expect($empleado->nombres)->toBe('Ana María')
        ->and($empleado->paterno)->toBe('Quispe')
        ->and($empleado->materno)->toBe('Mamani')
        ->and($empleado->ci)->toBe('7654321 LP')
        ->and($empleado->telefono)->toBe('71234567')
        ->and($empleado->fecha_ingreso->format('Y-m-d'))->toBe('2024-03-15');
});

test('cargo, sucursal, area and estado cannot be changed from the profile', function () {
    $user = User::factory()->create();
    $empleado = Empleado::factory()->create(['user_id' => $user->id, 'estado' => 'ACTIVO']);
    $original = $empleado->only(['cargo', 'sucursal_id', 'area_id', 'estado']);

    $this->actingAs($user)->patch('/profile/empleado', [
        'nombres' => $empleado->nombres,
        'ci' => $empleado->ci,
        'fecha_ingreso' => '01-02-2023',
        'cargo' => 'Otro cargo',
        'sucursal_id' => 999999,
        'area_id' => 999999,
        'estado' => 'INACTIVO',
    ])->assertSessionHasNoErrors();

    expect($empleado->fresh()->only(['cargo', 'sucursal_id', 'area_id', 'estado']))->toBe($original);
});

test('fecha de ingreso must use the dd-mm-yyyy format and not be in the future', function (string $fecha) {
    $user = User::factory()->create();
    $empleado = Empleado::factory()->create(['user_id' => $user->id]);

    $this->actingAs($user)->patch('/profile/empleado', [
        'nombres' => $empleado->nombres,
        'ci' => $empleado->ci,
        'fecha_ingreso' => $fecha,
    ])->assertSessionHasErrors('fecha_ingreso');
})->with([
    'formato Y-m-d' => '2024-03-15',
    'fecha inexistente' => '31-02-2024',
    'fecha futura' => fn () => now()->addDay()->format('d-m-Y'),
]);

test('the CI must stay unique among empleados', function () {
    $user = User::factory()->create();
    $empleado = Empleado::factory()->create(['user_id' => $user->id]);
    $otro = Empleado::factory()->create();

    $this->actingAs($user)->patch('/profile/empleado', [
        'nombres' => $empleado->nombres,
        'ci' => $otro->ci,
        'fecha_ingreso' => '01-02-2023',
    ])->assertSessionHasErrors('ci');
});

test('an account without a linked empleado cannot update labor data', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->patch('/profile/empleado', [
        'nombres' => 'Nadie',
        'ci' => '123',
        'fecha_ingreso' => '01-02-2023',
    ])->assertForbidden();
});
