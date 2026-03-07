// Bootstrap Table Localization for ebroker-admin
// This file handles dynamic localization of Bootstrap Table components

// Prevent auto-initialization of Bootstrap Table
$(document).ready(function() {
    // Get current locale (should be set in footer_script.blade.php)
    window.currentLocale = window.currentLocale || $('html').attr('lang') || 'en';
    
    // Wait for translations to be loaded
    var attempts = 0;
    var maxAttempts = 100; // 5 seconds max wait time
    
    var checkTranslations = function() {
        attempts++;
        
        if (typeof window.trans !== 'undefined' && typeof $.fn.bootstrapTable !== 'undefined') {
            initBootstrapTableWithLocalization();
        } else if (attempts < maxAttempts) {
            setTimeout(checkTranslations, 50);
        } else {
            console.warn('Bootstrap Table localization timed out - initializing with default locale');
            initBootstrapTableWithLocalization();
        }
    };
    
    checkTranslations();
});

function initBootstrapTableWithLocalization() {
    var currentLang = window.currentLocale || 'en';
    
    // Helper function to get translation
    function getTrans(key) {
        return window.trans && window.trans[key] ? window.trans[key] : null;
    }
    
    // Create locale object using translations loaded by Laravel
    var localeObject = {
        formatLoadingMessage: function () {
            return getTrans('Loading, please wait') || 'Loading, please wait';
        },
        formatRecordsPerPage: function (pageNumber) {
            var text = getTrans('rows per page') || 'rows per page';
            return pageNumber + ' ' + text;
        },
        formatShowingRows: function (pageFrom, pageTo, totalRows, totalNotFiltered) {
            var showing = getTrans('Showing') || 'Showing';
            var to = getTrans('to') || 'to';
            var of = getTrans('of') || 'of';
            var rows = getTrans('rows') || 'rows';
            var filtered = getTrans('filtered from') || 'filtered from';
            var total = getTrans('total rows') || 'total rows';
            
            if (totalNotFiltered !== undefined && totalNotFiltered > 0 && totalNotFiltered > totalRows) {
                return showing + ' ' + pageFrom + ' ' + to + ' ' + pageTo + ' ' + of + ' ' + totalRows + ' ' + rows + ' (' + filtered + ' ' + totalNotFiltered + ' ' + total + ')';
            }
            return showing + ' ' + pageFrom + ' ' + to + ' ' + pageTo + ' ' + of + ' ' + totalRows + ' ' + rows;
        },
        formatSRPaginationPreText: function () {
            return getTrans('previous page') || 'previous page';
        },
        formatSRPaginationPageText: function (page) {
            var toPage = getTrans('to page') || 'to page';
            return toPage + ' ' + page;
        },
        formatSRPaginationNextText: function () {
            return getTrans('next page') || 'next page';
        },
        formatDetailPagination: function (totalRows) {
            var showing = getTrans('Showing') || 'Showing';
            var rows = getTrans('rows') || 'rows';
            return showing + ' ' + totalRows + ' ' + rows;
        },
        formatSearch: function () {
            return getTrans('Search') || 'Search';
        },
        formatClearSearch: function () {
            return getTrans('Clear Search') || 'Clear Search';
        },
        formatNoMatches: function () {
            return getTrans('No matching records found') || 'No matching records found';
        },
        formatPaginationSwitch: function () {
            return getTrans('Hide/Show pagination') || 'Hide/Show pagination';
        },
        formatPaginationSwitchDown: function () {
            return getTrans('Show pagination') || 'Show pagination';
        },
        formatPaginationSwitchUp: function () {
            return getTrans('Hide pagination') || 'Hide pagination';
        },
        formatRefresh: function () {
            return getTrans('Refresh') || 'Refresh';
        },
        formatToggle: function () {
            return getTrans('Toggle') || 'Toggle';
        },
        formatToggleOn: function () {
            return getTrans('Show card view') || 'Show card view';
        },
        formatToggleOff: function () {
            return getTrans('Hide card view') || 'Hide card view';
        },
        formatColumns: function () {
            return getTrans('Columns') || 'Columns';
        },
        formatColumnsToggleAll: function () {
            return getTrans('Toggle all') || 'Toggle all';
        },
        formatFullscreen: function () {
            return getTrans('Fullscreen') || 'Fullscreen';
        },
        formatAllRows: function () {
            return getTrans('All') || 'All';
        }
    };
    
    // Ensure locales object exists and add current language locale
    $.fn.bootstrapTable.locales = $.fn.bootstrapTable.locales || {};
    $.fn.bootstrapTable.locales[currentLang] = localeObject;
    
    // Set default locale
    $.fn.bootstrapTable.defaults = $.extend({}, $.fn.bootstrapTable.defaults, {
        locale: currentLang
    });
    
    // Restore data-toggle attribute and initialize all tables
    $('[data-toggle-original="table"]').each(function() {
        var $table = $(this);
        
        // Restore the data-toggle attribute
        $table.attr('data-toggle', 'table').removeAttr('data-toggle-original');
        
        // Only initialize if not already initialized
        if (!$table.data('bootstrap.table')) {
            var tableOptions = $.extend({}, $table.data(), {
                locale: currentLang
            });
        
            $table.bootstrapTable(tableOptions);
        }
    });
    
}

// Handle language changes (if you have dynamic language switching)
$(document).on('languageChanged', function(e, newLang) {
    window.currentLocale = newLang;
    
    // Destroy and reinitialize all tables with new locale
    $('[data-toggle="table"]').each(function() {
        var $table = $(this);
        if ($table.data('bootstrap.table')) {
            var options = $table.bootstrapTable('getOptions');
            $table.bootstrapTable('destroy');
            options.locale = newLang;
            $table.bootstrapTable(options);
        }
    });
}); 