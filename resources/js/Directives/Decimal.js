// directives/decimal.js
export default {
  mounted(el, binding) {
    let decimals = binding.value ?? 2;
    const buildRegex = () => new RegExp(`^\\d*(\\.\\d{0,${decimals}})?$`);

    let isProcessing = false;

    const sanitize = (rawValue) => {
      let value = rawValue;

      // Reemplaza TODAS las comas por punto
      value = value.replace(/,/g, '.');

      // Elimina cualquier caracter que no sea dígito o punto
      // (esto ya excluye el signo "-", bloqueando negativos)
      value = value.replace(/[^\d.]/g, '');

      // Si hay más de un punto, deja solo el primero
      const parts = value.split('.');
      if (parts.length > 2) {
        value = parts[0] + '.' + parts.slice(1).join('');
      }

      // Limita la cantidad de decimales permitidos
      const regex = buildRegex();
      if (!regex.test(value)) {
        const [intPart, decPart] = value.split('.');
        value = decPart !== undefined
          ? `${intPart}.${decPart.slice(0, decimals)}`
          : intPart;
      }

      return value;
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

    // Bloquea directamente la tecla "-" (signo negativo) y "e" (notación científica)
    const keydownHandler = (e) => {
      if (e.key === '-' || e.key === 'e' || e.key === 'E' || e.key === '+') {
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

    // Permite actualizar la cantidad de decimales dinámicamente (v-decimal reactivo)
    el._updateDecimals = (newDecimals) => {
      decimals = newDecimals ?? 2;
    };

    el._decimalHandler = handler;
    el._keydownHandler = keydownHandler;
    el._pasteHandler = pasteHandler;

    el.addEventListener('input', handler);
    el.addEventListener('keydown', keydownHandler);
    el.addEventListener('paste', pasteHandler);
  },

  updated(el, binding) {
    if (el._updateDecimals) {
      el._updateDecimals(binding.value);
    }
  },

  unmounted(el) {
    el.removeEventListener('input', el._decimalHandler);
    el.removeEventListener('keydown', el._keydownHandler);
    el.removeEventListener('paste', el._pasteHandler);
    delete el._decimalHandler;
    delete el._keydownHandler;
    delete el._pasteHandler;
    delete el._updateDecimals;
  }
};
