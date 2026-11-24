const SAFE_EXTENSIONS = new Set([
    'pdf', 'txt', 'md', 'markdown', 'rtf', 'csv', 'tsv', 'log',
    'json', 'jsonl', 'xml', 'yaml', 'yml', 'toml', 'ini', 'cfg', 'conf',
    'sql', 'patch', 'diff', 'ipynb', 'tex', 'bib',
    'doc', 'docx', 'odt', 'ott', 'xls', 'xlsx', 'ods', 'ppt', 'pptx', 'odp',
    'epub', 'mobi', 'azw', 'azw3',
    'zip', '7z', 'rar', 'tar', 'gz', 'bz2', 'xz', 'zst', 'lz4',
    'jpg', 'jpeg', 'png', 'gif', 'webp', 'avif', 'bmp', 'tif', 'tiff',
    'heic', 'heif', 'ico',
    'mp3', 'wav', 'ogg', 'oga', 'opus', 'm4a', 'aac', 'flac', 'aiff', 'aif',
    'mp4', 'm4v', 'mov', 'mkv', 'webm', 'avi', 'mpeg', 'mpg', '3gp', '3g2',
    'ts', 'mts', 'm2ts',
    'woff', 'woff2', 'ttf', 'otf', 'eot',
    'blend', 'obj', 'stl', 'gltf', 'glb', 'fbx', 'dae',
    'dwg', 'dxf', 'step', 'stp', 'iges', 'igs',
    'psd', 'ai', 'eps', 'indd', 'sketch', 'fig', 'xd', 'xcf', 'kra',
    'vsdx', 'vsd', 'drawio',
    'java', 'kt', 'kts', 'c', 'h', 'cpp', 'hpp', 'cs', 'go', 'rs',
    'rb', 'swift', 'dart', 'vue', 'jsx', 'tsx', 'css', 'scss', 'less',
    'gradle', 'properties', 'env.example',
]);

const BLOCKED_EXTENSIONS = new Set([
    'exe', 'com', 'scr', 'msi', 'msp', 'dll', 'so', 'dylib', 'sys', 'drv',
    'bat', 'cmd', 'ps1', 'psm1', 'vbs', 'vbe', 'js', 'jse', 'mjs', 'cjs',
    'hta', 'wsf', 'wsh', 'sh', 'bash', 'zsh', 'fish',
    'php', 'phtml', 'phar', 'asp', 'aspx', 'jsp', 'cgi',
    'html', 'htm', 'xhtml', 'shtml', 'svg', 'xsl', 'xslt',
    'jar', 'war', 'ear', 'class', 'apk', 'aab', 'ipa', 'app',
    'deb', 'rpm', 'dmg', 'iso', 'img', 'vhd', 'vhdx',
    'reg', 'lnk', 'url', 'desktop', 'crx', 'xpi',
    'docm', 'dotm', 'xlsm', 'xlam', 'pptm', 'ppsm',
]);

const IMAGE_EXTENSIONS = new Set([
    'jpg', 'jpeg', 'png', 'gif', 'webp', 'avif', 'bmp', 'tif', 'tiff',
    'heic', 'heif', 'ico',
]);
const VIDEO_EXTENSIONS = new Set([
    'mp4', 'm4v', 'mov', 'mkv', 'webm', 'avi', 'mpeg', 'mpg', '3gp',
    '3g2', 'ts', 'mts', 'm2ts',
]);
const AUDIO_EXTENSIONS = new Set([
    'mp3', 'wav', 'ogg', 'oga', 'opus', 'm4a', 'aac', 'flac', 'aiff', 'aif',
]);

export function validateChatAttachment(file) {
    const name = String(file?.name || '').trim();

    if (
        !name
        || name.length > 255
        || /[\\/\u0000-\u001f\u007f]/.test(name)
    ) {
        return { valid: false, message: 'Недопустимое имя файла' };
    }

    const parts = name.toLowerCase().split('.');
    const extension = parts.pop();

    if (
        !extension
        || !SAFE_EXTENSIONS.has(extension)
        || parts.some(part => BLOCKED_EXTENSIONS.has(part))
    ) {
        return {
            valid: false,
            message: 'Этот тип файла запрещён. Выберите документ, изображение, аудио, видео или другой безопасный формат.',
        };
    }

    const mediaType = IMAGE_EXTENSIONS.has(extension)
        ? 'image'
        : VIDEO_EXTENSIONS.has(extension)
            ? 'video'
            : AUDIO_EXTENSIONS.has(extension)
                ? 'audio'
                : 'file';

    return { valid: true, extension, mediaType };
}

export function recordingFileName(mimeType, baseName) {
    const mime = String(mimeType || '').toLowerCase().split(';')[0].trim();
    const extension = mime.includes('mp4') ? 'm4a' : mime.includes('ogg') ? 'ogg' : 'webm';
    return `${baseName}.${extension}`;
}
