<script setup lang="ts">
import { cn } from '@/lib/style.utils';
import SignaturePadLib, { type PointGroup } from 'signature_pad';
import { onBeforeUnmount, onMounted, ref, watch, type HTMLAttributes } from 'vue';

export interface SignaturePadProps {
    class?: HTMLAttributes['class'];
    penColor?: string;
    backgroundColor?: string;
    minWidth?: number;
    maxWidth?: number;
    disabled?: boolean;
}

const props = withDefaults(defineProps<SignaturePadProps>(), {
    penColor: '#000000',
    backgroundColor: 'rgb(255, 255, 255)',
    minWidth: 0.5,
    maxWidth: 2.5,
    disabled: false,
});

const emit = defineEmits<{
    update: [data: { dataUrl: string; points: PointGroup[] } | null];
}>();

const canvasRef = ref<HTMLCanvasElement | null>(null);
const wrapperRef = ref<HTMLDivElement | null>(null);
const signaturePad = ref<SignaturePadLib | null>(null);
const isEmpty = ref(true);

const initSignaturePad = () => {
    if (!canvasRef.value) {
        return;
    }

    signaturePad.value = new SignaturePadLib(canvasRef.value, {
        penColor: props.penColor,
        backgroundColor: props.backgroundColor,
        minWidth: props.minWidth,
        maxWidth: props.maxWidth,
    });

    // Listen for stroke events - use both endStroke and afterUpdateStroke for better coverage
    signaturePad.value.addEventListener('endStroke', () => {
        isEmpty.value = signaturePad.value?.isEmpty() ?? true;
        emitUpdate();
    });

    signaturePad.value.addEventListener('afterUpdateStroke', () => {
        isEmpty.value = signaturePad.value?.isEmpty() ?? true;
    });

    resizeCanvas();
};

const resizeCanvas = () => {
    if (!canvasRef.value || !wrapperRef.value || !signaturePad.value) {
        return;
    }

    const ratio = Math.max(window.devicePixelRatio || 1, 1);
    const canvas = canvasRef.value;
    const wrapper = wrapperRef.value;

    canvas.width = wrapper.offsetWidth * ratio;
    canvas.height = wrapper.offsetHeight * ratio;
    canvas.getContext('2d')?.scale(ratio, ratio);

    signaturePad.value.clear();
    isEmpty.value = true;
};

const clear = () => {
    signaturePad.value?.clear();
    isEmpty.value = true;
    emit('update', null);
};

const emitUpdate = () => {
    if (signaturePad.value?.isEmpty()) {
        emit('update', null);
        return;
    }

    const dataUrl = signaturePad.value?.toDataURL('image/png') ?? '';
    const points = signaturePad.value?.toData() ?? [];

    emit('update', { dataUrl, points });
};

watch(
    () => props.disabled,
    (disabled) => {
        if (signaturePad.value) {
            if (disabled) {
                signaturePad.value.off();
            } else {
                signaturePad.value.on();
            }
        }
    },
);

onMounted(() => {
    initSignaturePad();
    window.addEventListener('resize', resizeCanvas);
});

onBeforeUnmount(() => {
    window.removeEventListener('resize', resizeCanvas);
    signaturePad.value?.off();
});

const getData = (): { dataUrl: string; points: PointGroup[] } | null => {
    if (!signaturePad.value || signaturePad.value.isEmpty()) {
        return null;
    }
    return {
        dataUrl: signaturePad.value.toDataURL('image/png'),
        points: signaturePad.value.toData(),
    };
};

defineExpose({ clear, isEmpty, getData });
</script>

<template>
    <div class="flex flex-col gap-2">
        <div
            ref="wrapperRef"
            :class="
                cn(
                    'relative h-40 w-full overflow-hidden rounded-lg border border-input bg-background transition-colors',
                    disabled && 'cursor-not-allowed opacity-50',
                    props.class,
                )
            "
        >
            <canvas
                ref="canvasRef"
                class="absolute inset-0 h-full w-full touch-none"
                :class="disabled ? 'pointer-events-none' : 'cursor-crosshair'"
            />
            <div v-if="isEmpty" class="pointer-events-none absolute inset-0 flex items-center justify-center">
                <span class="text-sm text-muted-foreground">Sign here</span>
            </div>
        </div>
        <button
            type="button"
            class="self-end text-xs text-muted-foreground hover:text-foreground disabled:cursor-not-allowed disabled:opacity-50"
            :disabled="isEmpty || disabled"
            @click="clear"
        >
            Clear signature
        </button>
    </div>
</template>
