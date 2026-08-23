// directives/integer-min.js
export default {
  mounted(el, binding) {
    let min = binding.value ?? 0;

    let isProcessing = false;

    const sanitize = (rawValue) => {
      let value = rawValue;

      // Elimina cualquier caracter que no sea dígito
      // (esto ya excluye el signo "-", bloqueando negativos, y el ".")
      value = value.replace(/[^\d]/g, '');

      return value;
    };

    const clampToMin = (rawValue) => {
      if (rawValue === '') return rawValue;
      const numeric = parseInt(rawValue, 10);
      return numeric < min ? String(min) : rawValue;
    };

    const handler = (e) => {
      if (isProcessing) return;

      const input = e.target;
      const newValue = sanitize(input.value);

      if (newValue !== input.value) {
        isProcessing = true;
        input.value = newValue;
        input.dispatchEvent(new Event('input'));
        isProcessing = false;
      }
    };

    // Bloquea directamente signos, punto decimal y notación científica
    const keydownHandler = (e) => {
      if (['-', '+', '.', ',', 'e', 'E'].includes(e.key)) {
        e.preventDefault();
      }
    };

    // Sanitiza también lo que se pega (paste), no solo lo que se teclea
    const pasteHandler = (e) => {
      e.preventDefault();
      const pasted = (e.clipboardData || window.clipboardData).getData('text');
      const clean = sanitize(pasted);
      document.execCommand('insertText', false, clean);
    };

    // Corrige al mínimo cuando el input pierde el foco
    const blurHandler = (e) => {
      const input = e.target;
      const clamped = clampToMin(input.value);

      if (clamped !== input.value) {
        input.value = clamped;
        input.dispatchEvent(new Event('input'));
      }
    };

    // Permite actualizar el mínimo dinámicamente (v-integer-min reactivo)
    el._updateMin = (newMin) => {
      min = newMin ?? 0;
    };

    el._integerHandler = handler;
    el._keydownHandler = keydownHandler;
    el._pasteHandler = pasteHandler;
    el._blurHandler = blurHandler;

    // Ayuda a que en móviles aparezca el teclado numérico
    if (!el.hasAttribute('inputmode')) el.setAttribute('inputmode', 'numeric');

    el.addEventListener('input', handler);
    el.addEventListener('keydown', keydownHandler);
    el.addEventListener('paste', pasteHandler);
    el.addEventListener('blur', blurHandler);
  },

  updated(el, binding) {
    if (el._updateMin) {
      el._updateMin(binding.value);
    }
  },

  unmounted(el) {
    el.removeEventListener('input', el._integerHandler);
    el.removeEventListener('keydown', el._keydownHandler);
    el.removeEventListener('paste', el._pasteHandler);
    el.removeEventListener('blur', el._blurHandler);
    delete el._integerHandler;
    delete el._keydownHandler;
    delete el._pasteHandler;
    delete el._blurHandler;
    delete el._updateMin;
  }
};
