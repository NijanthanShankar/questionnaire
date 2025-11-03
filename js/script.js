/**
 * CleanIndex Portal - Frontend JavaScript
 * Handles interactive features and AJAX requests
 */

(function($) {
    'use strict';
    
    // Document ready
    $(document).ready(function() {
        initializeFileUpload();
        initializeFormValidation();
        initializeTooltips();
    });
    
    /**
     * Initialize file upload handling
     */
    function initializeFileUpload() {
        const $fileAreas = $('.file-upload-area');
        
        $fileAreas.each(function() {
            const $area = $(this);
            const $input = $area.find('input[type="file"]');
            
            // Click to browse
            $area.on('click', function(e) {
                if ($(e.target).is('input')) return;
                $input.click();
            });
            
            // Drag and drop
            $area.on('dragover', function(e) {
                e.preventDefault();
                e.stopPropagation();
                $(this).addClass('dragover');
            });
            
            $area.on('dragleave', function(e) {
                e.preventDefault();
                e.stopPropagation();
                $(this).removeClass('dragover');
            });
            
            $area.on('drop', function(e) {
                e.preventDefault();
                e.stopPropagation();
                $(this).removeClass('dragover');
                
                const files = e.originalEvent.dataTransfer.files;
                if (files.length > 0) {
                    $input[0].files = files;
                    $input.trigger('change');
                }
            });
        });
    }
    
    /**
     * Form validation
     */
    function initializeFormValidation() {
        $('form.validate').on('submit', function(e) {
            let isValid = true;
            
            $(this).find('[required]').each(function() {
                const $field = $(this);
                const value = $field.val().trim();
                
                if (!value) {
                    $field.addClass('error');
                    isValid = false;
                } else {
                    $field.removeClass('error');
                }
            });
            
            // Email validation
            $(this).find('input[type="email"]').each(function() {
                const $field = $(this);
                const email = $field.val().trim();
                const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                
                if (email && !emailRegex.test(email)) {
                    $field.addClass('error');
                    alert('Please enter a valid email address');
                    isValid = false;
                }
            });
            
            if (!isValid) {
                e.preventDefault();
                $('html, body').animate({
                    scrollTop: $('.error').first().offset().top - 100
                }, 500);
            }
            
            return isValid;
        });
        
        // Remove error class on input
        $('input, select, textarea').on('input change', function() {
            $(this).removeClass('error');
        });
    }
    
    /**
     * Initialize tooltips
     */
    function initializeTooltips() {
        $('[title]').each(function() {
            $(this).attr('data-tooltip', $(this).attr('title'));
            $(this).removeAttr('title');
        });
    }
    
    /**
     * Show notification
     */
    window.showNotification = function(message, type = 'success') {
        const $notification = $('<div>')
            .addClass('alert alert-' + type)
            .text(message)
            .css({
                position: 'fixed',
                top: '20px',
                right: '20px',
                zIndex: 9999,
                minWidth: '300px',
                animation: 'slideIn 0.3s ease'
            });
        
        $('body').append($notification);
        
        setTimeout(function() {
            $notification.fadeOut(300, function() {
                $(this).remove();
            });
        }, 3000);
    };
    
    /**
     * Confirm dialog
     */
    window.confirmAction = function(message, callback) {
        if (confirm(message)) {
            callback();
        }
    };
    
    /**
     * Format file size
     */
    window.formatFileSize = function(bytes) {
        if (bytes === 0) return '0 Bytes';
        const k = 1024;
        const sizes = ['Bytes', 'KB', 'MB', 'GB'];
        const i = Math.floor(Math.log(bytes) / Math.log(k));
        return Math.round(bytes / Math.pow(k, i) * 100) / 100 + ' ' + sizes[i];
    };
    
    /**
     * AJAX file upload
     */
    window.uploadFile = function(file, type = 'assessment') {
        return new Promise((resolve, reject) => {
            const formData = new FormData();
            formData.append('action', 'cip_upload_file');
            formData.append('nonce', cipAjax.nonce);
            formData.append('file', file);
            formData.append('type', type);
            
            $.ajax({
                url: cipAjax.ajaxurl,
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                success: function(response) {
                    if (response.success) {
                        resolve(response.data);
                    } else {
                        reject(response.data);
                    }
                },
                error: function() {
                    reject('Upload failed');
                }
            });
        });
    };
    
    /**
     * Auto-save functionality
     */
    let autoSaveTimeout;
    window.enableAutoSave = function(formSelector, saveFunction, delay = 2000) {
        $(formSelector).find('input, select, textarea').on('input change', function() {
            clearTimeout(autoSaveTimeout);
            autoSaveTimeout = setTimeout(saveFunction, delay);
        });
    };
    
    /**
     * Progress bar animation
     */
    window.animateProgressBar = function(selector, targetValue, duration = 1000) {
        const $bar = $(selector);
        const startValue = parseInt($bar.css('width')) || 0;
        const startTime = Date.now();
        
        function update() {
            const elapsed = Date.now() - startTime;
            const progress = Math.min(elapsed / duration, 1);
            const currentValue = startValue + (targetValue - startValue) * progress;
            
            $bar.css('width', currentValue + '%');
            
            if (progress < 1) {
                requestAnimationFrame(update);
            }
        }
        
        requestAnimationFrame(update);
    };
    
    /**
     * Smooth scroll to element
     */
    window.scrollToElement = function(selector, offset = 0) {
        const $element = $(selector);
        if ($element.length) {
            $('html, body').animate({
                scrollTop: $element.offset().top - offset
            }, 500);
        }
    };
    
    /**
     * Debounce function
     */
    window.debounce = function(func, wait) {
        let timeout;
        return function executedFunction(...args) {
            const later = () => {
                clearTimeout(timeout);
                func(...args);
            };
            clearTimeout(timeout);
            timeout = setTimeout(later, wait);
        };
    };
    
    /**
     * Copy to clipboard
     */
    window.copyToClipboard = function(text) {
        const $temp = $('<textarea>');
        $('body').append($temp);
        $temp.val(text).select();
        document.execCommand('copy');
        $temp.remove();
        showNotification('Copied to clipboard!', 'success');
    };
    
    /**
     * Print element
     */
    window.printElement = function(selector) {
        const $element = $(selector);
        if ($element.length) {
            const printWindow = window.open('', '', 'height=600,width=800');
            printWindow.document.write('<html><head><title>Print</title>');
            printWindow.document.write('<link rel="stylesheet" href="' + cipAjax.styleUrl + '">');
            printWindow.document.write('</head><body>');
            printWindow.document.write($element.html());
            printWindow.document.write('</body></html>');
            printWindow.document.close();
            printWindow.print();
        }
    };
    
    /**
     * Loading overlay
     */
    window.showLoading = function(message = 'Loading...') {
        if ($('#cip-loading-overlay').length === 0) {
            const $overlay = $(`
                <div id="cip-loading-overlay" style="
                    position: fixed;
                    top: 0;
                    left: 0;
                    right: 0;
                    bottom: 0;
                    background: rgba(0, 0, 0, 0.5);
                    backdrop-filter: blur(5px);
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    z-index: 99999;
                ">
                    <div style="
                        background: white;
                        padding: 2rem;
                        border-radius: 12px;
                        text-align: center;
                    ">
                        <div class="spinner"></div>
                        <p style="margin-top: 1rem;">${message}</p>
                    </div>
                </div>
            `);
            $('body').append($overlay);
        }
    };
    
    window.hideLoading = function() {
        $('#cip-loading-overlay').fadeOut(300, function() {
            $(this).remove();
        });
    };
    
    /**
     * Table sorting
     */
    window.initializeTableSort = function(tableSelector) {
        $(tableSelector + ' th').css('cursor', 'pointer').on('click', function() {
            const $table = $(this).closest('table');
            const columnIndex = $(this).index();
            const $rows = $table.find('tbody tr').get();
            
            const isAscending = $(this).hasClass('sort-asc');
            
            $table.find('th').removeClass('sort-asc sort-desc');
            $(this).addClass(isAscending ? 'sort-desc' : 'sort-asc');
            
            $rows.sort(function(a, b) {
                const aValue = $(a).find('td').eq(columnIndex).text();
                const bValue = $(b).find('td').eq(columnIndex).text();
                
                if (isAscending) {
                    return aValue > bValue ? -1 : 1;
                } else {
                    return aValue < bValue ? -1 : 1;
                }
            });
            
            $.each($rows, function(index, row) {
                $table.find('tbody').append(row);
            });
        });
    };
    
})(jQuery);

// Vanilla JS utilities (no jQuery dependency)

/**
 * Get cookie value
 */
function getCookie(name) {
    const value = `; ${document.cookie}`;
    const parts = value.split(`; ${name}=`);
    if (parts.length === 2) return parts.pop().split(';').shift();
}

/**
 * Set cookie
 */
function setCookie(name, value, days = 7) {
    const expires = new Date();
    expires.setTime(expires.getTime() + days * 24 * 60 * 60 * 1000);
    document.cookie = `${name}=${value};expires=${expires.toUTCString()};path=/`;
}

/**
 * Local storage helpers
 */
const storage = {
    get: function(key) {
        try {
            return JSON.parse(localStorage.getItem(key));
        } catch (e) {
            return null;
        }
    },
    set: function(key, value) {
        try {
            localStorage.setItem(key, JSON.stringify(value));
            return true;
        } catch (e) {
            return false;
        }
    },
    remove: function(key) {
        localStorage.removeItem(key);
    }
};