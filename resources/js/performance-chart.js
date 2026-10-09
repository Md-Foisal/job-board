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
 * Colours come from the design tokens on :root, read when the chart is
 * drawn, so either theme gets its own: the series runs in the Sunset
 * gradient across the plot, as every coloured mark in the product does
 * (the heading set, which keeps 3:1 against the page -- WCAG 1.4.11 for
 * graphics), and the grid and labels take the line and muted-ink
 * tokens. The one series needs no legend, its heading names it. The table beside the canvas is the accessible view of the same
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

            const token = (name) => getComputedStyle(document.documentElement).getPropertyValue(name).trim()
            const stops = [token('--color-sunset-ink-1'), token('--color-sunset-ink-2'), token('--color-sunset-ink-3')]
            const grid = token('--color-line')
            const ink = token('--color-ink-muted')
            const surface = token('--color-canvas')

            // One gradient across the plot area, left to right, so a line
            // and a row of columns change colour the same way. Made per
            // draw, since the plot area is only known once laid out.
            const sunset = (alpha = 1) => ({ chart: { ctx, chartArea } }) => {
                if (! chartArea) {
                    return stops[1]
                }

                const gradient = ctx.createLinearGradient(chartArea.left, 0, chartArea.right, 0)
                const hex = (colour) => alpha === 1 ? colour : colour + Math.round(alpha * 255).toString(16).padStart(2, '0')

                gradient.addColorStop(0, hex(stops[0]))
                gradient.addColorStop(0.55, hex(stops[1]))
                gradient.addColorStop(1, hex(stops[2]))

                return gradient
            }
            const line = sunset()

            const lineDataset = {
                borderColor: line,
                borderWidth: 2,
                borderJoinStyle: 'round',
                borderCapStyle: 'round',
                // A wash, not a block: the line carries the data.
                backgroundColor: sunset(0.1),
                fill: 'origin',
                tension: 0,
                pointRadius: 0,
                pointHoverRadius: 5,
                pointHoverBackgroundColor: stops[1],
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
