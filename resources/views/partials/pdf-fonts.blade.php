<style>
    @font-face {
        font-family: 'CvNotoSansSC';
        font-style: normal;
        font-weight: normal;
        src: url("{{ 'file://' . str_replace('\\', '/', storage_path('fonts/NotoSansSC-Regular.ttf')) }}") format('truetype');
    }
    /* The available regular CJK face also supplies glyphs in bold text. */
    @font-face {
        font-family: 'CvNotoSansSC';
        font-style: normal;
        font-weight: bold;
        src: url("{{ 'file://' . str_replace('\\', '/', storage_path('fonts/NotoSansSC-Regular.ttf')) }}") format('truetype');
    }
    body { font-family: 'DejaVu Sans', 'CvNotoSansSC', sans-serif; }
</style>
