// directives/max-length.js
export default {
  mounted(el, binding) {
    let maxLength = binding.value ?? 255;

    let isProcessing = false;

    const sanitize = (rawValue) => {
      // Recorta el texto a la longitud máxima permitida
      return rawValue.length > maxLength ? rawValue.slice(0, maxLength) : rawValue;
    };

    const handler = (e) => {
      if (isProcessing) return;

      const input = e.target;
      const newValue = sanitize(input.value);

      if (newValue !== input.value) {
        isProcessing = true;
        const cursor = input.selectionStart ?? newValue.length;
        input.value = newValue;
        // Mantiene la posición del cursor cuando es posible
        input.setSelectionRange(cursor, cursor);
        input.dispatchEvent(new Event('input'));
        isProcessing = false;
      }
    };

    // Bloquea directamente nuevas teclas cuando ya se alcanzó el límite
    // (deja pasar teclas de control como borrar, navegar, etc.)
    const keydownHandler = (e) => {
      const controlKeys = [
        'Backspace', 'Delete', 'ArrowLeft', 'ArrowRight',
        'ArrowUp', 'ArrowDown', 'Tab', 'Home', 'End', 'Enter'
      ];
      const isCtrlCombo = e.ctrlKey || e.metaKey;

      if (controlKeys.includes(e.key) || isCtrlCombo) return;

      const input = e.target;
      const hasSelection = input.selectionStart !== input.selectionEnd;

      if (!hasSelection && input.value.length >= maxLength) {
        e.preventDefault();
      }
    };

    // Sanitiza también lo que se pega (paste), no solo lo que se teclea
    const pasteHandler = (e) => {
      const input = e.target;
      const start = input.selectionStart ?? input.value.length;
      const end = input.selectionEnd ?? input.value.length;
      const currentLength = input.value.length - (end - start);
      const availableSpace = maxLength - currentLength;

      if (availableSpace <= 0) {
        e.preventDefault();
        return;
      }

      e.preventDefault();
      const pasted = (e.clipboardData || window.clipboardData).getData('text');
      const clean = pasted.slice(0, availableSpace);
      document.execCommand('insertText', false, clean);
    };

    // Permite actualizar la longitud máxima dinámicamente (v-max-length reactivo)
    el._updateMaxLength = (newMaxLength) => {
      maxLength = newMaxLength ?? 255;
      // Si el valor actual excede el nuevo máximo, lo recorta de inmediato
      if (el.value.length > maxLength) {
        el.value = el.value.slice(0, maxLength);
        el.dispatchEvent(new Event('input'));
      }
    };

    el._maxLengthHandler = handler;
    el._keydownHandler = keydownHandler;
    el._pasteHandler = pasteHandler;

    // Ayuda nativa del navegador (contador, validación de formulario, etc.)
    el.setAttribute('maxlength', maxLength);

    el.addEventListener('input', handler);
    el.addEventListener('keydown', keydownHandler);
    el.addEventListener('paste', pasteHandler);
  },

  updated(el, binding) {
    if (el._updateMaxLength) {
      el._updateMaxLength(binding.value);
      el.setAttribute('maxlength', binding.value ?? 255);
    }
  },

  unmounted(el) {
    el.removeEventListener('input', el._maxLengthHandler);
    el.removeEventListener('keydown', el._keydownHandler);
    el.removeEventListener('paste', el._pasteHandler);
    delete el._maxLengthHandler;
    delete el._keydownHandler;
    delete el._pasteHandler;
    delete el._updateMaxLength;
  }
};
