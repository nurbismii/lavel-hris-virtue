(function() {
    function resetSubmitLocks() {
        document.querySelectorAll('form[data-submit-lock]').forEach(function(form) {
            delete form.dataset.submitting;
            form.removeAttribute('aria-busy');
            form.querySelectorAll('[data-submit-original]').forEach(function(button) {
                button.disabled = false;
                button.innerHTML = button.dataset.submitOriginal;
                delete button.dataset.submitOriginal;
            });
        });
    }

    window.addEventListener('pageshow', resetSubmitLocks);
    document.addEventListener('submit', function(event) {
        const form = event.target;
        if (!form.matches('form[data-submit-lock]') || event.defaultPrevented) { return; }
        if (form.dataset.submitting) { event.preventDefault(); return; }
        form.dataset.submitting = 'true';
        form.setAttribute('aria-busy', 'true');
        form.querySelectorAll('button[type="submit"]').forEach(function(button) {
            button.dataset.submitOriginal = button.innerHTML;
            button.disabled = true;
            button.textContent = button.dataset.loadingText || 'Menyimpan...';
        });
    });

    const sidebar = document.getElementById('appSidebar');
    const pageHeading = document.querySelector('.main-panel > .container .ui-page-title, .main-panel > .container .page-header h3, .main-panel > .container .page-header h4, .page-inner > .d-flex h3, .page-inner > .d-flex h4, .dashboard-home-page .dashboard-title');
    if (pageHeading && document.title.trim() === 'V-People · PT VDNI') {
        document.title = pageHeading.textContent.trim() + ' | V-People · PT VDNI';
    }
    const mobile = window.matchMedia('(max-width: 991.98px)');
    let menuTrigger = null;

    function setMenuOpen(open) {
        document.documentElement.classList.toggle('app-menu-open', open);
        document.querySelector('.main-panel').inert = open;
        sidebar.setAttribute('role', open ? 'dialog' : 'navigation');
        if (open) { sidebar.setAttribute('aria-modal', 'true'); } else { sidebar.removeAttribute('aria-modal'); }
        document.querySelectorAll('[data-app-menu-toggle][aria-expanded]').forEach(function(button) {
            button.setAttribute('aria-expanded', String(open));
        });
        if (open) {
            window.requestAnimationFrame(function() { sidebar.querySelector('[data-app-menu-toggle]').focus(); });
        } else if (menuTrigger) {
            menuTrigger.focus();
        }
    }

    document.addEventListener('click', function(event) {
        const toggle = event.target.closest('[data-app-menu-toggle]');
        if (toggle && sidebar && mobile.matches) {
            const open = !document.documentElement.classList.contains('app-menu-open');
            if (open) { menuTrigger = toggle; }
            setMenuOpen(open);
        } else if (sidebar && sidebar.contains(event.target) && event.target.closest('a[href]:not([data-bs-toggle])')) {
            setMenuOpen(false);
        }
    });

    document.addEventListener('keydown', function(event) {
        if (!sidebar || !document.documentElement.classList.contains('app-menu-open')) { return; }
        if (event.key === 'Escape') { setMenuOpen(false); }
        if (event.key === 'Tab') {
            const controls = Array.from(sidebar.querySelectorAll('a[href],button:not([disabled])')).filter(function(element) {
                return element.getClientRects().length > 0;
            });
            if (controls.length && (!sidebar.contains(document.activeElement) || document.activeElement === (event.shiftKey ? controls[0] : controls[controls.length - 1]))) {
                event.preventDefault();
                (event.shiftKey ? controls[controls.length - 1] : controls[0]).focus();
            }
        }
    });

    window.addEventListener('resize', function() {
        if (!mobile.matches && document.documentElement.classList.contains('app-menu-open')) { setMenuOpen(false); }
    });
})();

(function() {
    const IMAGE_MIME_TYPES = ['image/jpeg', 'image/jpg', 'image/png', 'image/webp'];
    const TARGET_BYTES = Math.round(1.8 * 1024 * 1024);
    const MAX_DIMENSION = 2400;
    const MIN_DIMENSION = 1000;
    const MIN_QUALITY = 0.45;

    function isCompressibleImage(file) {
        return file && IMAGE_MIME_TYPES.includes((file.type || '').toLowerCase());
    }

    function readFileAsDataUrl(file) {
        return new Promise((resolve, reject) => {
            const reader = new FileReader();
            reader.onload = () => resolve(reader.result);
            reader.onerror = () => reject(new Error('Gagal membaca file gambar.'));
            reader.readAsDataURL(file);
        });
    }

    function loadImage(source) {
        return new Promise((resolve, reject) => {
            const image = new Image();
            image.onload = () => resolve(image);
            image.onerror = () => reject(new Error('Gagal memuat file gambar.'));
            image.src = source;
        });
    }

    function canvasToBlob(canvas, quality) {
        return new Promise((resolve, reject) => {
            canvas.toBlob((blob) => {
                if (!blob) {
                    reject(new Error('Gagal membuat file hasil kompres.'));
                    return;
                }

                resolve(blob);
            }, 'image/jpeg', quality);
        });
    }

    function drawImageToCanvas(image, width, height) {
        const canvas = document.createElement('canvas');
        canvas.width = width;
        canvas.height = height;

        const context = canvas.getContext('2d', { alpha: false });
        context.fillStyle = '#ffffff';
        context.fillRect(0, 0, width, height);
        context.drawImage(image, 0, 0, width, height);

        return canvas;
    }

    function scaleDimensions(width, height, maxDimension) {
        if (Math.max(width, height) <= maxDimension) {
            return { width, height };
        }

        const ratio = maxDimension / Math.max(width, height);

        return {
            width: Math.max(1, Math.round(width * ratio)),
            height: Math.max(1, Math.round(height * ratio)),
        };
    }

    async function compressImageFile(file) {
        if (!isCompressibleImage(file) || file.size <= TARGET_BYTES) {
            return file;
        }

        const imageSource = await readFileAsDataUrl(file);
        const image = await loadImage(imageSource);
        let dimensions = scaleDimensions(image.width, image.height, MAX_DIMENSION);
        let quality = 0.9;
        let blob = null;

        while (true) {
            const canvas = drawImageToCanvas(image, dimensions.width, dimensions.height);
            blob = await canvasToBlob(canvas, quality);

            if (blob.size <= TARGET_BYTES) {
                break;
            }

            if (quality > MIN_QUALITY) {
                quality = Math.max(MIN_QUALITY, quality - 0.08);
                continue;
            }

            if (Math.max(dimensions.width, dimensions.height) <= MIN_DIMENSION) {
                break;
            }

            dimensions = {
                width: Math.max(1, Math.round(dimensions.width * 0.9)),
                height: Math.max(1, Math.round(dimensions.height * 0.9)),
            };
            quality = 0.82;
        }

        if (!blob || blob.size >= file.size) {
            return file;
        }

        const originalName = file.name.replace(/\.[^.]+$/, '');

        return new File(
            [blob],
            originalName + '.jpg',
            {
                type: 'image/jpeg',
                lastModified: Date.now(),
            }
        );
    }

    async function compressInputFiles(input) {
        if (!input.files || !input.files.length || typeof DataTransfer === 'undefined') {
            return {
                compressedCount: 0,
                savedBytes: 0,
            };
        }

        const dataTransfer = new DataTransfer();
        let compressedCount = 0;
        let savedBytes = 0;

        for (const file of Array.from(input.files)) {
            const compressedFile = await compressImageFile(file);

            if (compressedFile !== file) {
                compressedCount += 1;
                savedBytes += Math.max(file.size - compressedFile.size, 0);
            }

            dataTransfer.items.add(compressedFile);
        }

        input.files = dataTransfer.files;

        return {
            compressedCount,
            savedBytes,
        };
    }

    function toggleFormSubmitting(form, isSubmitting) {
        const buttons = form.querySelectorAll('button[type="submit"], input[type="submit"]');

        buttons.forEach((button) => {
            if (!button.dataset.originalText) {
                button.dataset.originalText = button.tagName === 'INPUT' ? button.value : button.innerHTML;
            }

            button.disabled = isSubmitting;

            if (isSubmitting) {
                if (button.tagName === 'INPUT') {
                    button.value = 'Memproses gambar...';
                } else {
                    button.innerHTML = 'Memproses gambar...';
                }
            } else if (button.dataset.originalText) {
                if (button.tagName === 'INPUT') {
                    button.value = button.dataset.originalText;
                } else {
                    button.innerHTML = button.dataset.originalText;
                }
            }
        });
    }

    document.addEventListener('submit', async function(event) {
        const form = event.target;

        if (!(form instanceof HTMLFormElement) || form.dataset.autoCompressImages !== 'true') {
            return;
        }

        if (form.dataset.compressionDone === 'true') {
            return;
        }

        event.preventDefault();

        if (form.dataset.compressionInProgress === 'true') {
            return;
        }

        form.dataset.compressionInProgress = 'true';
        toggleFormSubmitting(form, true);

        try {
            const inputs = form.querySelectorAll('input[type="file"][data-compress-images="true"]');
            let compressedCount = 0;
            let savedBytes = 0;

            for (const input of Array.from(inputs)) {
                const result = await compressInputFiles(input);
                compressedCount += result.compressedCount;
                savedBytes += result.savedBytes;
            }

            if (compressedCount > 0 && window.$ && $.notify) {
                $.notify({
                    icon: 'fa fa-compress',
                    title: 'Kompresi aktif',
                    message: `${compressedCount} gambar dikompres sebelum upload. Hemat sekitar ${(savedBytes / (1024 * 1024)).toFixed(2)} MB.`
                }, {
                    type: 'info',
                    placement: {
                        from: 'top',
                        align: 'right'
                    },
                    delay: 3000
                });
            }

            form.dataset.compressionDone = 'true';
            HTMLFormElement.prototype.submit.call(form);
        } catch (error) {
            form.dataset.compressionInProgress = 'false';
            toggleFormSubmitting(form, false);

            window.AppDialog.alert(
                'Kompresi gagal',
                error.message || 'Gagal memproses gambar sebelum upload.',
                'error'
            );
        }
    });
})();
