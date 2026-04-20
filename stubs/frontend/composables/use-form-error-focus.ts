import { nextTick } from 'vue';

export function useFormErrorFocus() {
    const focusFirstError = async (errors: Record<string, string>) => {
        if (Object.keys(errors).length === 0) {
            return;
        }

        // Wait for DOM to update with error states
        await nextTick();

        const forms = Array.from(document.querySelectorAll('form'));

        for (const form of forms) {
            const firstErrorElement = form.querySelector(
                '.error, [aria-invalid="true"], :invalid',
            ) as HTMLElement | null;

            if (firstErrorElement && firstErrorElement.offsetParent !== null) {
                firstErrorElement.focus();
                firstErrorElement.scrollIntoView({
                    behavior: 'smooth',
                    block: 'center',
                });
                break;
            }
        }
    };

    return {
        focusFirstError,
    };
}
