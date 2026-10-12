/**
 * Núcleo de Apache ECharts con SOLO las piezas que usa el panel.
 *
 * ECharts completo pesa ~1 MB; importando desde `echarts/core` y registrando a
 * mano los tipos de gráfico y componentes, Vite deja afuera todo lo demás
 * (mapas, 3D, gauges, etc.). Este archivo se carga con `import()` desde
 * `BaseChart.vue`, así que queda en su propio chunk y las pantallas sin
 * gráficos no lo descargan.
 *
 * Para usar un tipo nuevo (p. ej. un heatmap) hay que registrarlo acá — y
 * agregar su forma a `BaseChart.vue`, que sigue siendo el único lugar donde se
 * instancia un gráfico.
 */
import * as echarts from 'echarts/core';
import { BarChart, LineChart } from 'echarts/charts';
import {
    AriaComponent,
    GridComponent,
    MarkAreaComponent,
    MarkLineComponent,
    TooltipComponent,
} from 'echarts/components';
import { CanvasRenderer } from 'echarts/renderers';

echarts.use([
    LineChart,
    BarChart,
    GridComponent,
    TooltipComponent,
    MarkLineComponent,
    MarkAreaComponent,
    AriaComponent,
    CanvasRenderer,
]);

export default echarts;
