/* ==========================================================================
   REDSALUD - JAVASCRIPT GENERAL
   ========================================================================== */

document.addEventListener("DOMContentLoaded", function () {

    /* ======================================================================
       CREAR / EDITAR EXPEDIENTE
       ====================================================================== */

    const origen = document.getElementById("origen_documento");
    const areaGroup = document.getElementById("areaEmisoraGroup");
    const areaSelect = document.getElementById("id_area_emisora");

    if (origen && areaGroup && areaSelect) {

        function actualizarAreaEmisora() {

            if (origen.value === "INTERNO") {
                areaGroup.classList.remove("is-hidden");
                areaSelect.required = true;
            } else {
                areaGroup.classList.add("is-hidden");
                areaSelect.required = false;
                areaSelect.value = "";
            }
        }

        origen.addEventListener("change", actualizarAreaEmisora);

        actualizarAreaEmisora();
    }


    /* ======================================================================
       ARCHIVO PDF
       ====================================================================== */

    const archivo = document.getElementById("archivo_principal_pdf");
    const selectedFile = document.getElementById("selectedFile");
    const selectedFileName = document.getElementById("selectedFileName");
    const selectedFileSize = document.getElementById("selectedFileSize");

    if (archivo && selectedFile && selectedFileName && selectedFileSize) {

        archivo.addEventListener("change", function () {

            if (!archivo.files.length) {
                selectedFile.hidden = true;
                return;
            }

            const file = archivo.files[0];

            selectedFileName.textContent = file.name;
            selectedFileSize.textContent = formatearTamano(file.size);
            selectedFile.hidden = false;
        });
    }


    /* ======================================================================
       FORMULARIO CREAR EXPEDIENTE
       ====================================================================== */

    const formulario = document.getElementById("formCrearExpediente");
    const botonGuardar = document.getElementById("btnGuardarExpediente");
    const archivoInput = document.getElementById("archivo_principal_pdf");
    const errorArchivo = document.getElementById("errorArchivo");

    if (formulario && botonGuardar) {

        formulario.addEventListener("submit", function () {

            botonGuardar.disabled = true;
            botonGuardar.innerHTML = "<span>✓</span> Guardando...";
        });

        if (archivoInput && errorArchivo) {

            archivoInput.addEventListener("change", function () {

                if (archivoInput.files.length) {

                    errorArchivo.style.display = "none";

                    const caja = document.querySelector(".file-upload-box");

                    if (caja) {
                        caja.style.borderColor = "";
                    }
                }
            });
        }
    }


    /* ======================================================================
       FORMULARIO EDITAR EXPEDIENTE
       ====================================================================== */

    const formularioEditar = document.getElementById("formEditarExpediente");
    const botonEditar = document.getElementById("btnGuardarExpediente");

    if (formularioEditar && botonEditar) {

        formularioEditar.addEventListener("submit", function () {

            botonEditar.disabled = true;
            botonEditar.innerHTML = "<span>✓</span> Guardando cambios...";
        });
    }


    /* ======================================================================
       CONTADOR DE CARACTERES - NOTIFICACIONES
       ====================================================================== */

    const textarea = document.getElementById("mensaje");
    const counter = document.getElementById("charCounter");

    if (textarea && counter) {

        function actualizarContador() {

            const longitud = textarea.value.length;

            counter.textContent = longitud + " / 500";

            counter.classList.remove("warning", "danger");

            if (longitud >= 450) {
                counter.classList.add("danger");
            } else if (longitud >= 400) {
                counter.classList.add("warning");
            }
        }

        textarea.addEventListener("input", actualizarContador);

        actualizarContador();
    }


    /* ======================================================================
       SELECTOR DINÁMICO DE VÍNCULO - NOTIFICACIONES
       ====================================================================== */

    const tipoVinculo = document.getElementById("tipo_vinculo");
    const grupoVinculo = document.getElementById("grupoVinculo");
    const idVinculo = document.getElementById("id_vinculo");
    const labelVinculo = document.getElementById("labelVinculo");
    const hintVinculo = document.getElementById("hintVinculo");

    if (
        tipoVinculo &&
        grupoVinculo &&
        idVinculo &&
        labelVinculo &&
        hintVinculo
    ) {

        const datos = window.datosVinculo || {
            expediente: [],
            anexo: [],
            proveido: []
        };


        function actualizarSelectorVinculo() {

            const tipo = tipoVinculo.value;


            /* --------------------------------------------------------------
               SIN VÍNCULO
               -------------------------------------------------------------- */

            if (tipo === "ninguno") {

                grupoVinculo.style.display = "none";

                idVinculo.innerHTML =
                    '<option value="">— Seleccione —</option>';

                return;
            }


            /* --------------------------------------------------------------
               MOSTRAR SELECTOR
               -------------------------------------------------------------- */

            grupoVinculo.style.display = "block";


            /* --------------------------------------------------------------
               TEXTOS SEGÚN EL TIPO
               -------------------------------------------------------------- */

            const labels = {
                expediente: "Seleccione el expediente:",
                anexo: "Seleccione el anexo:",
                proveido: "Seleccione el proveído:"
            };

            const hints = {
                expediente:
                    "Se vinculará el expediente seleccionado.",

                anexo:
                    "Se vinculará el expediente al que pertenece el anexo.",

                proveido:
                    "Se vinculará el expediente al que pertenece el proveído."
            };


            labelVinculo.textContent =
                labels[tipo] || "Seleccione:";

            hintVinculo.textContent =
                hints[tipo] || "";


            /* --------------------------------------------------------------
               CARGAR OPCIONES
               -------------------------------------------------------------- */

            const lista = datos[tipo] || [];

            idVinculo.innerHTML =
                '<option value="">— Seleccione —</option>';


            lista.forEach(function (item) {

                const opcion = document.createElement("option");

                opcion.value = item.id;
                opcion.textContent = item.texto;

                idVinculo.appendChild(opcion);
            });
        }


        tipoVinculo.addEventListener(
            "change",
            actualizarSelectorVinculo
        );

        actualizarSelectorVinculo();
    }


    /* ======================================================================
       MENSAJES DEL SISTEMA
       ====================================================================== */

    const alertas = document.querySelectorAll(".alert");

    if (alertas.length) {

        alertas.forEach(function (alerta) {

            setTimeout(function () {

                alerta.style.opacity = "0";
                alerta.style.transform = "translateY(-5px)";

                setTimeout(function () {
                    alerta.remove();
                }, 300);

            }, 5000);
        });
    }

});


/* ==========================================================================
   FORMATEAR TAMAÑO DE ARCHIVO
   ========================================================================== */

function formatearTamano(bytes) {

    if (bytes === 0) {
        return "0 Bytes";
    }

    const unidades = [
        "Bytes",
        "KB",
        "MB",
        "GB"
    ];

    const indice = Math.floor(
        Math.log(bytes) / Math.log(1024)
    );

    return (
        parseFloat(
            (bytes / Math.pow(1024, indice)).toFixed(2)
        ) +
        " " +
        unidades[indice]
    );
}


/* ==========================================================================
   CONFIRMACIÓN DE RECEPCIÓN / OBSERVACIÓN
   ========================================================================== */

function confirmarAccion(accion) {

    const titulo = accion === "RECEPCIONAR"
        ? "¿Confirmas que deseas RECEPCIONAR este movimiento?"
        : "¿Deseas marcar este movimiento como OBSERVADO?";

    Swal.fire({
        title: "Aviso",
        text: titulo,
        icon: "question",
        showCancelButton: true,
        confirmButtonColor: "#2b5278",
        cancelButtonColor: "#6c757d",
        confirmButtonText: "Aceptar",
        cancelButtonText: "Cancelar",
        reverseButtons: true
    }).then((result) => {

        if (result.isConfirmed) {

            document.getElementById("accionRecepcion").value = accion;
            document.getElementById("formRecepcion").submit();
        }
    });
}


/* ==========================================================================
   CONFIRMACIÓN DE ELIMINACIÓN
   ========================================================================== */

function confirmarEliminacion(url) {

    /* ----------------------------------------------------------------------
       RESPALDO: si SweetAlert2 no está cargado
       ---------------------------------------------------------------------- */

    if (typeof Swal === "undefined") {

        return confirm(
            "¿Está seguro de eliminar este expediente?\n\n" +
            "Esta acción no se puede deshacer."
        );
    }


    /* ----------------------------------------------------------------------
       VENTANA DE CONFIRMACIÓN
       ---------------------------------------------------------------------- */

    Swal.fire({

        title: "¿Eliminar expediente?",

        html:
            '<div class="redsalud-swal-text">' +

                '<p>' +
                    "Estás a punto de eliminar este expediente." +
                "</p>" +

                '<p class="swal-delete-warning">' +
                    "Esta acción no se puede deshacer." +
                "</p>" +

            "</div>",

        icon: "warning",

        showCancelButton: true,

        confirmButtonColor: "#dc3545",
        cancelButtonColor: "#6c757d",

        confirmButtonText: "Sí, eliminar",
        cancelButtonText: "Cancelar",

        reverseButtons: true,

        focusCancel: true,

        allowOutsideClick: false,
        allowEscapeKey: true,

        customClass: {

            popup: "redsalud-swal-popup",

            title: "redsalud-swal-title",

            htmlContainer: "redsalud-swal-text",

            confirmButton: "redsalud-swal-confirm",

            cancelButton: "redsalud-swal-cancel"
        }

    }).then(function (result) {

        if (result.isConfirmed) {

            /* --------------------------------------------------------------
               ESTADO DE PROCESAMIENTO
               -------------------------------------------------------------- */

            Swal.fire({

                title: "Eliminando...",

                text: "Procesando la eliminación del expediente.",

                allowOutsideClick: false,
                allowEscapeKey: false,

                showConfirmButton: false,

                didOpen: function () {
                    Swal.showLoading();
                }

            });

            window.location.href = url;
        }
    });

    return false;
}


/* ==========================================================================
   IMPRESIÓN DE EXPEDIENTES
   ========================================================================== */

function imprimirExpediente() {

    window.print();
}