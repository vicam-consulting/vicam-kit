/**
 * Copy text to clipboard.
 * Uses modern clipboard API first, falls back to execCommand if not supported.
 */
export async function copyToClipboard(text: string) {
    try {
        // Try modern clipboard API first, fall back to execCommand
        if (navigator.clipboard && window.isSecureContext) {
            await navigator.clipboard.writeText(text);
        } else {
            fallbackCopy(text);
        }
    } catch {
        fallbackCopy(text);
    }
}

function fallbackCopy(text: string): void {
    const textArea = document.createElement('textarea');
    textArea.value = text;
    textArea.style.position = 'fixed';
    textArea.style.left = '-9999px';
    document.body.appendChild(textArea);
    textArea.select();

    try {
        document.execCommand('copy');
    } finally {
        document.body.removeChild(textArea);
    }
}
