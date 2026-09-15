import richTextEditor from './rich-text-editor'

document.addEventListener('alpine:init', () => {
    window.Alpine.data('richTextEditor', richTextEditor)
})
