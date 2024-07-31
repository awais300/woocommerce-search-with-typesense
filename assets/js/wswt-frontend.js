jQuery(document).ready(function($) {
    var searchInput = $('#woocommerce-product-search-field-0');
    var resultsContainer = $('#live-search-results');
    var typingTimer;
    var doneTypingInterval = 300;

    searchInput.on('input', function() {
        clearTimeout(typingTimer);
        var query = $(this).val();
        
        if (query.length >= 3) {
            typingTimer = setTimeout(function() {
                performSearch(query);
            }, doneTypingInterval);
        } else {
            resultsContainer.empty();
        }
    });

    function performSearch(query) {
        $.ajax({
            url: wc_live_search_params.ajax_url,
            type: 'POST',
            dataType: 'html',
            data: {
                action: 'wc_live_search',
                security: wc_live_search_params.nonce,
                query: query
            },
            success: function(response) {
                console.log('AJAX Response:', response);
                if (response) {
                    resultsContainer.html(response);
                } else {
                    resultsContainer.html('<p>No results found</p>');
                }
            },
            error: function(jqXHR, textStatus, errorThrown) {
                console.error('AJAX Error:', textStatus, errorThrown);
                resultsContainer.html('<p>An error occurred. Please try again.</p>');
            }
        });
    }
});