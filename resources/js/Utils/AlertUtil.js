import Swal from "sweetalert2";


export const showToast = (message, type = "success") => {
    const toast = Swal.mixin({
        toast: true,
        position: "top-end",
        showConfirmButton: false,
        timer: 3000,
        timerProgressBar: true,
        didOpen: (toast) => {
            toast.addEventListener("mouseenter", Swal.stopTimer);
            toast.addEventListener("mouseleave", Swal.resumeTimer);
        },
    });

    toast.fire({
        icon: type,
        title: message,
    });
};

export const showError = (errors)=>{
    let message = '';
    if (typeof errors === 'string') {
        message = errors;
    } else if (Array.isArray(errors)) {
        message = errors.join('<br>');
    } else if (typeof errors === 'object' && errors !== null) {
        message = Object.values(errors).map((error) => `<p class=" text-danger mb-0">${error}</p>`).join('');
    }

    // showToast(message, 'error');
    Swal.fire({
        title: 'Error',
        html: message,
        icon: 'error',
        confirmButtonText: 'Aceptar'
    });
}

export const confirmation = async (
    message = "¿Está seguro?",
    title = "Confirmación",
    txtBtn = "Si, eliminar",
) => {
    const result = await Swal.fire({
        title: title,
        html: message,
        icon: "warning",
        showCancelButton: true,
        confirmButtonText: txtBtn,
        cancelButtonText: "Cancelar",
    });

    return result.isConfirmed;
};


