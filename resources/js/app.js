import richTextEditor from './rich-text-editor'

document.addEventListener('alpine:init', () => {
    window.Alpine.data('richTextEditor', richTextEditor)
})

/**
 * Take the user to the first thing that is wrong.
 *
 * Livewire renders each message next to its own field, which is right, but
 * on a form several screens tall the submit button is nowhere near the
 * field that failed -- press it and the page appears to do nothing at all.
 * Any component that refuses a save dispatches `form-invalid`; this moves
 * the view to the first message and puts the cursor in the control it
 * belongs to, which is what every long form people actually use does.
 */
window.addEventListener('form-invalid', () => {
    requestAnimationFrame(() => {
        const message = [...document.querySelectorAll('[data-flux-error]')]
            .find((el) => el.textContent.trim() !== '')

        if (! message) return

        const field = message.closest('[data-flux-field]') ?? message.parentElement

        field.scrollIntoView({ behavior: 'smooth', block: 'center' })
        field.querySelector('input, select, textarea, [contenteditable="true"]')
            ?.focus({ preventScroll: true })
    })
})
