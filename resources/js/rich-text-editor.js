import { Editor } from '@tiptap/core'
import StarterKit from '@tiptap/starter-kit'
import Link from '@tiptap/extension-link'

/**
 * A headless editor rather than a ready-made one: TipTap ships behaviour
 * and no appearance, so the toolbar and the writing surface are ours and
 * inherit the same design tokens as every other control on the page. A
 * pre-styled editor would have arrived looking like someone else's app.
 *
 * It writes plain HTML back to whatever is holding the value -- a Livewire
 * property when there is one, otherwise a hidden input so ordinary forms
 * work unchanged. What comes out is never trusted: the server sanitizes it
 * on the way in regardless.
 */
export default function richTextEditor({ content = '', wireModel = null, headings = true }) {
    return {
        editor: null,
        html: content,
        active: {},

        init() {
            this.editor = new Editor({
                element: this.$refs.surface,
                extensions: [
                    StarterKit.configure({
                        heading: headings ? { levels: [3, 4] } : false,
                    }),
                    // Autolink rather than a toolbar button: turning a typed
                    // address into a link is what people expect anyway, and
                    // the alternative was a native window.prompt, which is
                    // exactly the borrowed-browser-chrome look this app has
                    // been getting rid of elsewhere.
                    Link.configure({ openOnClick: false, autolink: true }),
                ],
                content: this.html,
                editorProps: {
                    attributes: {
                        class: 'prose-editor focus:outline-none min-h-40 px-3 py-2',
                    },
                },
                onUpdate: ({ editor }) => this.push(editor.getHTML()),
                onSelectionUpdate: () => this.refreshActive(),
                onTransaction: () => this.refreshActive(),
            })

            this.refreshActive()
        },

        destroy() {
            this.editor?.destroy()
        },

        /**
         * An empty document still serialises as "<p></p>", which would save
         * as content and defeat a "required" rule on the field.
         */
        push(html) {
            this.html = this.editor?.isEmpty ? '' : html

            if (wireModel) {
                this.$wire.set(wireModel, this.html, false)
            }
        },

        refreshActive() {
            if (!this.editor) return

            this.active = {
                bold: this.editor.isActive('bold'),
                italic: this.editor.isActive('italic'),
                bulletList: this.editor.isActive('bulletList'),
                orderedList: this.editor.isActive('orderedList'),
                h3: this.editor.isActive('heading', { level: 3 }),
                h4: this.editor.isActive('heading', { level: 4 }),
            }
        },

        run(command) {
            const chain = this.editor.chain().focus()

            ({
                bold: () => chain.toggleBold().run(),
                italic: () => chain.toggleItalic().run(),
                bulletList: () => chain.toggleBulletList().run(),
                orderedList: () => chain.toggleOrderedList().run(),
                h3: () => chain.toggleHeading({ level: 3 }).run(),
                h4: () => chain.toggleHeading({ level: 4 }).run(),
            })[command]?.()
        },
    }
}
