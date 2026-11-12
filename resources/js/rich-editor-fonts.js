// Font family and size marks for the Filament rich editor. Built into resources/js/dist by `npm run build:editor`.
import { Extension } from '@tiptap/core'
import { FontFamily, FontSize, TextStyle } from '@tiptap/extension-text-style'

export default Extension.create({
    name: 'fonts',

    addExtensions() {
        return [TextStyle, FontFamily, FontSize]
    },
})
