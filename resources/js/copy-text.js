/**
 * A copy button that only says "Copied" when the text really was copied.
 *
 * The Clipboard API exists only on secure (HTTPS) pages, and even there the
 * browser can refuse the write, so the promise is awaited and a failure is
 * shown as one: the person is told to copy it by hand instead of being left
 * with a button that seemed to work. A success clears itself after a moment;
 * a failure stays until the next try, so there is time to read it.
 */
export default function copyText() {
    let timer = null

    return {
        copyState: 'idle',

        async copy(text) {
            clearTimeout(timer)

            try {
                if (! navigator.clipboard) {
                    throw new Error('The clipboard is not available on this page.')
                }

                await navigator.clipboard.writeText(text)
                this.copyState = 'copied'
                timer = setTimeout(() => { this.copyState = 'idle' }, 2000)
            } catch {
                this.copyState = 'failed'
            }
        },
    }
}
