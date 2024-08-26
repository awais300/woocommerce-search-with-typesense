jQuery(document).ready(function($) {
    var $buttons = $('#test-connection, #index-products, #force-reindex-products');

    $('#test-connection').click(function() {
        disableButtons();
        $('#connection-result').hide(); // Hide the connection result div
        $('#spinner').show(); // Show spinner

        $.ajax({
            url: TS_LOCAL.ajax_url,
            type: 'POST',
            data: {
                action: 'test_typesense_connection',
                security: TS_LOCAL.test_typesense_connection_nonce
            },
            success: function(response) {
                if (response.success) {
                    $('#connection-result').html('<p style="color: green;">' + response.data + '</p>').show();
                } else {
                    $('#connection-result').html('<p style="color: red;">' + response.data + '</p>').show();
                }
                enableButtons();
                $('#spinner').hide(); // Hide spinner
            },
            error: function() {
                enableButtons();
                $('#spinner').hide(); // Hide spinner in case of error
            }
        });
    });

    $('#index-products, #force-reindex-products').click(function() {
        disableButtons();
        $('#indexing-progress').hide(); // Hide the indexing progress div
        $('#spinner').show(); // Show spinner

        var forceReindex = $(this).attr('id') === 'force-reindex-products';
        indexProducts(forceReindex);
    });

    var PROCCESSED_COUNT = 0;
    function indexProducts(forceReindex) {
        var action = forceReindex ? 'force_reindex_products' : 'index_products';
        var nonce = forceReindex ? TS_LOCAL.force_reindex_products_nonce : TS_LOCAL.index_products_nonce;

        $.ajax({
            url: TS_LOCAL.ajax_url,
            type: 'POST',
            data: {
                action: action,
                security: nonce
            },
            success: function(response) {
                if (response.status === 'in_progress') {
                    //PROCCESSED_COUNT = PROCCESSED_COUNT + response.batch_count;
                    var processed_message  = response.total_count + ' Left to Index';

                    $('#indexing-progress').html(processed_message).show();
                    // If not completed, call the function again
                    setTimeout(function() { indexProducts(false); }, 100);
                } else if (response.status === 'error') {
                    $('#indexing-progress').html(response.message).show();
                } else {
                    // Indexing is complete
                    $('#indexing-progress').html('Indexing completed!').show();
                    enableButtons();
                    $('#spinner').hide(); // Hide spinner
                }
            },
            error: function(jqXHR, textStatus, errorThrown) {
                $('#indexing-progress').html('An error occurred: ' + textStatus + ' - ' + errorThrown).show();
                enableButtons();
                $('#spinner').hide(); // Hide spinner in case of error
            }
        });
    }

    function disableButtons() {
        $buttons.prop('disabled', true);
    }

    function enableButtons() {
        $buttons.prop('disabled', false);
    }
});