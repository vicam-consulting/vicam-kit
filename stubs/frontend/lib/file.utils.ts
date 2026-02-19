const imageExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg', 'bmp'];

type DocumentType = 'image' | 'pdf' | 'other';

const getFileExtension = (file: File | null, url: string | null): string => {
    if (file) return file.name.split('.').pop()?.toLowerCase() ?? '';
    if (url) return url.split('?')[0].split('.').pop()?.toLowerCase() ?? '';
    return '';
};

/**
 * Check if a file is an image based on MIME type or extension.
 * Used to determine whether to show an image preview or a document icon.
 */
export function isImageFile(file: File | null, url: string | null): boolean {
    const extension = getFileExtension(file, url);

    if (file) {
        return file.type.startsWith('image/') || imageExtensions.includes(extension);
    }

    if (url) {
        return imageExtensions.includes(extension);
    }

    return false;
}

export function isPdfFile(file: File | null, url: string | null): boolean {
    const extension = getFileExtension(file, url);
    return extension.toLowerCase() === 'pdf';
}

export function getDocumentType(file: File | null, url: string | null): DocumentType {
    if (isImageFile(file, url)) {
        return 'image';
    }

    if (isPdfFile(file, url)) {
        return 'pdf';
    }

    return 'other';
}

/**
 * Format file size in bytes to human-readable format.
 */
export function formatFileSize(bytes: number | null | undefined): string {
    if (!bytes || bytes === 0) {
        return '0 B';
    }

    const k = 1024;
    const sizes = ['B', 'KB', 'MB', 'GB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));

    return `${parseFloat((bytes / Math.pow(k, i)).toFixed(2))} ${sizes[i] ?? 'B'}`;
}
