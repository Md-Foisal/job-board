import { Editor } from '@tiptap/core'
import StarterKit from '@tiptap/starter-kit'

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
 *
 * The Editor instance lives in this closure and NOT on the returned data
 * object, which is deliberate and load-bearing. Alpine wraps everything it
 * is handed in a reactive Proxy; reached through that Proxy, every
 * ProseMirror object comes back as a different identity than the one the
 * editor holds internally. ProseMirror compares a transaction's starting
 * document against its own by identity, so a proxied editor throws
 * "Applying a mismatched transaction" on every single command and the
 * whole toolbar dies. Vue solves this with markRaw; Alpine has no such
 * escape hatch, so the instance simply never enters the reactive graph.
 * Only `html` and `active` -- plain values the template binds to -- do.
 */
export default function richTextEditor({ content = '', wireModel = null, headings = true }) {
    let editor = null

    return {
        html: content,
        active: {},

        init() {
            editor = new Editor({
                element: this.$refs.surface,
                extensions: [
                    StarterKit.configure({
                        heading: headings ? { levels: [3, 4] } : false,
                        // StarterKit already carries the link extension, so it
                        // is configured here rather than registered a second
                        // time: two copies trigger a duplicate-name warning and
                        // leave it unclear which options win. Autolink rather
                        // than a toolbar button: turning a typed address into a
                        // link is what people expect anyway, and the
                        // alternative was a native window.prompt. openOnClick
                        // stays off so clicking a link while writing never
                        // navigates away from an unsaved form.
                        link: { openOnClick: false, autolink: true },
                    }),
                ],
                content: this.html,
                editorProps: {
                    attributes: {
                        class: 'prose-editor focus:outline-none min-h-40 px-3 py-2',
                    },
                },
                onUpdate: ({ editor: instance }) => this.push(instance.getHTML()),
                onSelectionUpdate: () => this.refreshActive(),
                onTransaction: () => this.refreshActive(),
            })

            this.refreshActive()
        },

        destroy() {
            editor?.destroy()
            editor = null
        },

        /**
         * An empty document still serialises as "<p></p>", which would save
         * as content and defeat a "required" rule on the field.
         */
        push(html) {
            this.html = editor?.isEmpty ? '' : html

            if (wireModel) {
                this.$wire.set(wireModel, this.html, false)
            }
        },

        refreshActive() {
            if (!editor) return

            this.active = {
                bold: editor.isActive('bold'),
                italic: editor.isActive('italic'),
                bulletList: editor.isActive('bulletList'),
                orderedList: editor.isActive('orderedList'),
                h3: editor.isActive('heading', { level: 3 }),
                h4: editor.isActive('heading', { level: 4 }),
            }
        },

        run(command) {
            if (!editor) return

            const chain = editor.chain().focus()

            // Named map, not a bare parenthesised object on the next line:
            // automatic semicolon insertion would glue `({...})` onto the
            // statement above and call its result, killing every button.
            const commands = {
                bold: () => chain.toggleBold().run(),
                italic: () => chain.toggleItalic().run(),
                bulletList: () => chain.toggleBulletList().run(),
                orderedList: () => chain.toggleOrderedList().run(),
                h3: () => chain.toggleHeading({ level: 3 }).run(),
                h4: () => chain.toggleHeading({ level: 4 }).run(),
            }

            commands[command]?.()
        },
    }
}
