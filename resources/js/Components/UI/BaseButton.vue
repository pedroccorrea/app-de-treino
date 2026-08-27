<script setup>
import { computed, ref } from 'vue';

const props = defineProps({
    // primary | secondary | danger | ghost
    variant: {
        type: String,
        default: 'primary',
    },
    // default (48/56px per variant) | hero (68px — the one oversized CTA in the app)
    size: {
        type: String,
        default: 'default',
    },
    // Shows the light-sweep trail used on the app's single highest-emphasis CTA.
    ripple: {
        type: Boolean,
        default: false,
    },
    iconOnly: {
        type: Boolean,
        default: false,
    },
    disabled: {
        type: Boolean,
        default: false,
    },
    type: {
        type: String,
        default: 'button',
    },
});

const emit = defineEmits(['click']);

const rippleKey = ref(0);

const handleClick = (event) => {
    if (props.ripple) rippleKey.value++;
    emit('click', event);
};

const variantClasses = {
    primary: 'border border-accent bg-accent-muted text-accent-text-strong shadow-[0_0_40px_var(--accent-glow)] hover:brightness-110 active:brightness-95',
    secondary: 'border border-border-subtle bg-transparent text-text-primary hover:border-border-accent',
    danger: 'border border-danger bg-transparent text-danger hover:brightness-125',
    ghost: 'border-0 bg-transparent text-text-secondary hover:text-text-primary',
};

const sizeClasses = computed(() => {
    if (props.size === 'hero') return 'h-[68px] rounded-radius-lg gap-3';
    if (props.iconOnly) return 'h-12 w-12 rounded-radius-md';
    return props.variant === 'primary' ? 'h-14 rounded-radius-md gap-2' : 'h-12 rounded-radius-md gap-2';
});

const paddingClasses = computed(() => (props.iconOnly ? 'p-0' : 'px-5'));
</script>

<template>
    <button
        :type="type"
        :disabled="disabled"
        @click="handleClick"
        :class="[
            'relative flex items-center justify-center overflow-hidden text-[15px] font-medium transition disabled:opacity-60',
            sizeClasses,
            paddingClasses,
            variantClasses[variant],
        ]"
    >
        <span v-if="ripple" :key="rippleKey" class="ripple-trail" aria-hidden="true"></span>
        <slot />
    </button>
</template>

<style scoped>
.ripple-trail {
    position: absolute;
    left: 0;
    right: 0;
    top: 50%;
    height: 2px;
    background: linear-gradient(90deg, transparent, var(--accent-text-soft), transparent);
    animation: ripple-trail 0.7s cubic-bezier(0.22, 1, 0.36, 1) forwards;
    pointer-events: none;
}

@keyframes ripple-trail {
    0% {
        transform: scaleX(0.4);
        opacity: 0;
    }
    35% {
        opacity: 1;
    }
    100% {
        transform: scaleX(1.6);
        opacity: 0;
    }
}
</style>
