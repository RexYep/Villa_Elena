{{-- Kapalit ng native na confirm(). Dalawang gamit:

     1. Form: `<form data-confirm="Delete this review?">` — hinaharang ang
        submit, nagtatanong, at itinutuloy ang PAREHONG submit kapag pumayag.
        Opsyonal: `data-confirm-label="Delete"` para sa teksto ng butones.
     2. Script: `if (!await confirmDialog('Remove this block?')) return;`

     `<dialog>` + showModal() ang gamit, hindi Bootstrap modal: nasa "top
     layer" ito ng browser, kaya lumalabas ito sa ibabaw ng mga sariling
     modal ng mga pahina (calendar, payments) anuman ang z-index nila, at
     kasama na ang Esc at ang pagkulong ng focus. --}}
<dialog id="confirmDialog" class="confirm-dialog" aria-labelledby="confirmDialogMsg">
    <p id="confirmDialogMsg" class="confirm-dialog-msg"></p>
    <div class="confirm-dialog-actions">
        <button type="button" class="confirm-dialog-btn" data-confirm-cancel>Cancel</button>
        <button type="button" class="confirm-dialog-btn is-primary" data-confirm-ok>Confirm</button>
    </div>
</dialog>

<style>
    .confirm-dialog {
        width: min(420px, calc(100vw - 32px));
        padding: 24px;
        border: none;
        border-radius: 14px;
        background: #fff;
        color: #1f2937;
        box-shadow: 0 20px 50px rgba(0, 0, 0, .25);
        overscroll-behavior: contain;
    }

    .confirm-dialog::backdrop {
        background: rgba(15, 23, 42, .5);
    }

    .confirm-dialog-msg {
        margin: 0 0 20px;
        font-size: 15px;
        line-height: 1.55;
        overflow-wrap: anywhere;
    }

    .confirm-dialog-actions {
        display: flex;
        justify-content: flex-end;
        gap: 10px;
    }

    .confirm-dialog-btn {
        min-height: 40px;
        padding: 9px 18px;
        border: 1px solid #d1d5db;
        border-radius: 9px;
        background: #fff;
        color: #374151;
        font: inherit;
        font-size: 14px;
        font-weight: 600;
        cursor: pointer;
    }

    .confirm-dialog-btn:hover {
        background: #f3f4f6;
    }

    .confirm-dialog-btn.is-primary {
        border-color: #dc2626;
        background: #dc2626;
        color: #fff;
    }

    .confirm-dialog-btn.is-primary:hover {
        background: #b91c1c;
    }

    .confirm-dialog-btn:focus-visible {
        outline: 2px solid #1d4ed8;
        outline-offset: 2px;
    }
</style>

<script>
    (function() {
        const dlg = document.getElementById('confirmDialog');
        const msg = document.getElementById('confirmDialogMsg');
        const okBtn = dlg.querySelector('[data-confirm-ok]');
        const cancelBtn = dlg.querySelector('[data-confirm-cancel]');
        let resolver = null;

        window.confirmDialog = function(message, options) {
            // Lumang browser na walang <dialog>: bumalik sa native.
            if (typeof dlg.showModal !== 'function') {
                return Promise.resolve(window.confirm(message));
            }

            msg.textContent = message;
            okBtn.textContent = (options && options.confirmLabel) || 'Confirm';
            dlg.returnValue = '';
            dlg.showModal();
            // Sa Cancel ang focus, hindi sa Confirm: ang Enter sa isang
            // tanong na nakakasira ay hindi dapat "oo" ang ibig sabihin.
            cancelBtn.focus();

            return new Promise(function(resolve) {
                resolver = resolve;
            });
        };

        okBtn.addEventListener('click', function() {
            dlg.close('ok');
        });
        cancelBtn.addEventListener('click', function() {
            dlg.close('cancel');
        });
        // Kasama rito ang Esc — `close` ang huling pangyayari sa lahat ng daan.
        dlg.addEventListener('close', function() {
            if (resolver) {
                resolver(dlg.returnValue === 'ok');
                resolver = null;
            }
        });

        // Capture phase + stopPropagation: walang ibang submit listener ng
        // form ang dapat tumakbo (hal. pag-disable ng butones) hangga't
        // hindi pa pumapayag ang tao.
        document.addEventListener('submit', function(e) {
            const form = e.target;
            const message = form.dataset ? form.dataset.confirm : null;
            if (!message || form.dataset.confirmed === '1') return;

            e.preventDefault();
            e.stopPropagation();
            const submitter = e.submitter;

            window.confirmDialog(message, {
                confirmLabel: form.dataset.confirmLabel
            }).then(function(ok) {
                if (!ok) return;
                form.dataset.confirmed = '1';
                if (submitter) {
                    form.requestSubmit(submitter);
                } else {
                    form.requestSubmit();
                }
                delete form.dataset.confirmed;
            });
        }, true);
    })();
</script>
