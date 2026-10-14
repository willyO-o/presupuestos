/**
 * Series de proyección para BaseChart: la línea punteada y su banda (rango
 * probable), alineadas sobre el mismo eje que el histórico.
 *
 * La proyección arranca en el último dato real que USÓ la recta, para que se
 * vea de dónde sale. Si el mes en curso quedó fuera del cálculo (está
 * incompleto, ver PeriodoReporte::pronostico), la línea no sale de su valor
 * parcial —se leería como una caída que no existe— sino del último mes
 * completo, y pasa por el cierre estimado de ese mes (`metodo.cierre_estimado`).
 *
 * @param {Array<number|null>} historico  Un valor por mes del periodo.
 * @param {Array<{valor:number, inferior:number, superior:number}>} proyeccion
 * @param {Object|null} metodo  Ficha del método que manda el servidor.
 * @param {string} nombre  Nombre de la serie punteada en la leyenda.
 * @returns {Array<Object>} Series listas para BaseChart (vacío sin proyección).
 */
export function seriesProyectadas(historico, proyeccion, metodo = null, nombre = 'Proyección') {
    if (!proyeccion?.length) {
        return [];
    }

    const cierre = metodo?.cierre_estimado ?? [];
    const limite = historico.length - cierre.length;
    const ancla = historico.findLastIndex((v, i) => i < limite && v !== null && v !== undefined);
    const valorAncla = ancla >= 0 ? historico[ancla] : null;

    const tramo = (clave) => historico.map((_, i) => {
        if (i === ancla) {
            return valorAncla;
        }

        return i >= limite ? cierre[i - limite][clave] : null;
    });

    return [
        { nombre, punteada: true, datos: [...tramo('valor'), ...proyeccion.map((p) => p.valor)] },
        {
            nombre: 'Rango probable',
            tipo: 'banda',
            inferior: [...tramo('inferior'), ...proyeccion.map((p) => p.inferior)],
            superior: [...tramo('superior'), ...proyeccion.map((p) => p.superior)],
        },
    ];
}
