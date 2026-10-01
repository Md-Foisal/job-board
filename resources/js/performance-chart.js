/**
 * One series over time, drawn with Chart.js, for the employer's analytics:
 * a line for counts that run high (views), columns for small ones that
 * would otherwise zigzag between zero and one (applications).
 *
 * Chart.js is loaded only when a chart is on the page, so every other
 * page keeps a small bundle. As with the rich text editor, the Chart
 * instance lives in this closure and never on the returned object: Alpine
 * would wrap it in a reactive Proxy, and Chart.js mutates its own state
 * far too much to be proxied safely.
 *
 * Colours come from the design tokens on :root, so the line is the brand
 * teal in either theme; the one series needs no legend, its heading names
 * it. The table beside the canvas is the accessible view of the same
 * numbers, since a canvas says nothing to a screen reader.
 */
export default function performanceChart({ type = 'line', labels = [], dates = [], values = [], label = '' }) {
    let chart = null

    return {
        showTable: false,

        async init() {
            const {
                Chart, LineController, LineElement, PointElement, BarController, BarElement,
                LinearScale, CategoryScale, Tooltip, Filler,
            } = await import('chart.js')

            Chart.register(LineController, LineElement, PointElement, BarController, BarElement, LinearScale, CategoryScale, Tooltip, Filler)

            const dark = document.documentElement.classList.contains('dark')
            const token = (name) => getComputedStyle(document.documentElement).getPropertyValue(name).trim()
            const line = token(dark ? '--color-brand-500' : '--color-brand-600')
            const grid = token(dark ? '--color-zinc-800' : '--color-zinc-200')
            const ink = token(dark ? '--color-zinc-400' : '--color-zinc-500')
            const surface = token(dark ? '--color-zinc-900' : '--color-white') || (dark ? '#171717' : '#ffffff')

            const lineDataset = {
                borderColor: line,
                borderWidth: 2,
                borderJoinStyle: 'round',
                borderCapStyle: 'round',
                // A wash, not a block: the line carries the data.
                backgroundColor: line.endsWith(')') ? line.replace(/\)$/, ' / 0.1)') : `${line}1a`,
                fill: 'origin',
                tension: 0,
                pointRadius: 0,
                pointHoverRadius: 5,
                pointHoverBackgroundColor: line,
                pointHoverBorderColor: surface,
                pointHoverBorderWidth: 2,
                pointHitRadius: 12,
            }

            const barDataset = {
                backgroundColor: line,
                hoverBackgroundColor: line,
                // Rounded at the data end, square on the baseline.
                borderRadius: { topLeft: 4, topRight: 4 },
                borderSkipped: 'start',
                maxBarThickness: 24,
                categoryPercentage: 0.9,
                barPercentage: 0.85,
            }

            chart = new Chart(this.$refs.canvas, {
                type,
                data: {
                    labels,
                    datasets: [{ label, data: values, ...(type === 'bar' ? barDataset : lineDataset) }],
                },
                options: {
                    maintainAspectRatio: false,
                    animation: false,
                    interaction: { mode: 'index', intersect: false },
                    plugins: {
                        legend: { display: false },
                        tooltip: {
                            displayColors: false,
                            callbacks: {
                                title: (items) => dates[items[0].dataIndex] ?? items[0].label,
                            },
                        },
                    },
                    scales: {
                        x: {
                            grid: { display: false },
                            border: { color: grid },
                            ticks: { color: ink, maxTicksLimit: 7, maxRotation: 0 },
                        },
                        y: {
                            beginAtZero: true,
                            grid: { color: grid, lineWidth: 1 },
                            border: { display: false },
                            ticks: { color: ink, precision: 0, maxTicksLimit: 5 },
                        },
                    },
                },
            })
        },

        destroy() {
            chart?.destroy()
            chart = null
        },
    }
}
