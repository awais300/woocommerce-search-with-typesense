jQuery(document).ready(function($) {
    function showLoadingOverlay() {
        $('#loading-overlay').show();
    }

    function hideLoadingOverlay() {
        $('#loading-overlay').hide();
    }

    $('.woocommerce-ordering').on('submit', function(e) {
        e.preventDefault();
    });

    // Function to get URL parameters
    function getUrlParameter(name) {
        name = name.replace(/[\[]/, '\\[').replace(/[\]]/, '\\]');
        var regex = new RegExp('[\\?&]' + name + '=([^&#]*)');
        var results = regex.exec(location.search);
        return results === null ? '' : decodeURIComponent(results[1].replace(/\+/g, ' '));
    }

    // Function to perform search
    function performSearch(requestedPage) {
        console.log("performSearch called");
        showLoadingOverlay(); // Show overlay before AJAX request

        var attributes = {};
        var category = '';
        var auctionsOnly = $('#auctionsonly').hasClass('switch-on');
        var orderBy = $('.nice-select.orderby .current').data('value');
        var paged = requestedPage || parseInt($('.woocommerce-pagination .current').data('page')) || 1;


        console.log(orderBy);

        // Get current URL parameters
        var urlParams = new URLSearchParams(window.location.search);

        if(!orderBy) {
            orderBy =  urlParams.get('orderby');
        }

        console.log(orderBy);

        // Remove rel_search parameter
        urlParams.delete('rel_search');

        // Set rel_method parameter
        urlParams.set('rel_method', 'ajax');

        // Collect selected attributes
        $('.product-attribute-filter').each(function() {
            var name = $(this).find('input:first, select:first').attr('name').replace('[]', '');
            var isGradeFilter = name === 'pa_grade';

            console.log("Processing attribute:", name, "isGradeFilter:", isGradeFilter);

            if (isGradeFilter) {
                var selectedValue = $(this).find('select').val();

                console.log("Grade filter value:", selectedValue);
                if (selectedValue) {
                    urlParams.set(name, selectedValue);
                    attributes[name] = selectedValue;
                } else {
                    urlParams.delete(name);
                    delete attributes[name];
                }
            } else {
                var values = [];
                $(this).find('input:checked').each(function() {
                    values.push($(this).val());
                    $(this).parent().addClass('checked');
                });

                $(this).find('input:not(:checked)').each(function() {
                    $(this).parent().removeClass('checked');
                });

                console.log("Multi select values:", values);
                if (values.length > 0) {
                    urlParams.set(name, values.join(','));
                    attributes[name] = values;
                } else {
                    urlParams.delete(name);
                    delete attributes[name];
                }
            }
        });

        // Check if orderby should be removed
        if (!auctionsOnly && (orderBy === 'auction_started' || orderBy === 'auction_end')) {
            urlParams.delete('orderby');
            orderBy = null;
        }

        console.log("Final URL params:", urlParams.toString());

        // Construct the new URL
        var newUrl = window.location.pathname + '?' + urlParams.toString();
        console.log("New URL:", newUrl);

        // Update the URL
        history.pushState(null, null, newUrl);

        // Get the selected category
        var checkedCategory = $('.product-category-filter input:checked');
        if (checkedCategory.length > 0) {
            category = checkedCategory.val();
            checkedCategory.parent().addClass('checked');
        }

        var uncheckedCategory = $('.product-category-filter input:not(:checked)');
        if (uncheckedCategory.length > 0) {
            uncheckedCategory.parent().removeClass('checked');
        }

        if (ajax_object.seller_id) {
            category = $('li.comic-seller-cat a.active').data('cat-slug');

            $('.categories_list ul li:first-child a:contains("Show All")').parent().remove();
            var currentUrl = window.location.origin + window.location.pathname;
            // Insert the "Show All" link with the current URL
            $('.categories_list ul').prepend('<li class="parent_cat"><a class="" href="' + currentUrl + '">Show All</a></li>');
        }

        // Update URL parameters
        if (category) {
            urlParams.set('product_cat', category);
        } else {
            urlParams.delete('product_cat');
        }

        if (auctionsOnly) {
            urlParams.set('auctions_only', '1');
        } else {
            urlParams.delete('auctions_only');
        }


        if (orderBy) {
            urlParams.set('orderby', orderBy);
        } else {
            urlParams.delete('orderby');
        }

        urlParams.set('cur_page', paged);

        // Construct the new URL
        var newUrl = window.location.pathname + '?' + urlParams.toString();
        history.pushState(null, null, newUrl);

        // Perform AJAX request
        $.ajax({
            url: ajax_object.ajax_url,
            method: 'POST',
            data: {
                action: 'typesense_search',
                category: category,
                attributes: attributes,
                auctions_only: auctionsOnly,
                orderby: orderBy,
                cur_page: paged,
                seller_id: ajax_object.seller_id,
                search_query: urlParams.get('s') // Include the search query from the URL
            },
            success: function(response) {
                if (response.success) {
                    var $container = $('#mbf_products');
                    $container.empty();
                    $container.html(response.data.html);
                    $('.woocommerce-ordering select').niceSelect();

                    // Maintain orderby selection
                    var currentOrderby = urlParams.get('orderby');
                    if (currentOrderby) {
                        $('.nice-select.orderby .option[data-value="' + currentOrderby + '"]').addClass('selected').siblings().removeClass('selected');
                        selectedOrderby();
                    }

                    // Update URL without reloading the page
                    urlParams.set('cur_page', paged);
                    if (orderBy) {
                        urlParams.set('orderby', orderBy);
                    }

                    toggleAuctionOptions();

                    var newUrl = window.location.pathname + '?' + urlParams.toString();
                    history.pushState(null, null, newUrl);

                } else {
                    console.error('Search failed:', response.data);
                }
                hideLoadingOverlay(); // Hide overlay on success
            },
            error: function(xhr, status, error) {
                console.error('Search error:', error);
                hideLoadingOverlay(); // Hide overlay on error
            }
        });
    }

    // Event listener for category checkboxes
    $('.product-category-filter input').on('change', function() {
        // Uncheck other category checkboxes
        $('.product-category-filter input').not(this).prop('checked', false);
        performSearch(1);
    });

    // Event listeners for other filters
    $('.product-attribute-filter input').on('change', function() {
        performSearch(1);
    });

    // Event listener for grade filter select
    $('.product-attribute-filter select').on('change', function() {
        performSearch(1);
    });

    $('#auctionsonly').on('click', function(e) {
        e.preventDefault();
        $(this).toggleClass('switch-on switch-off');
        performSearch(1);
    });

    // Event listener for custom dropdown change

    $(document).on('click', '.nice-select.orderby .option', function(e) {
        e.preventDefault();
        $(this).addClass('selected').siblings().removeClass('selected');
        selectedOrderby();
        performSearch(1);
    });

    $(document).on('click', '.woocommerce-pagination.ajax .page-numbers', function(e) {
        e.preventDefault();
        var requestedPage;
        if ($(this).hasClass('next')) {
            requestedPage = parseInt($('.woocommerce-pagination .current').data('page')) + 1;
        } else if ($(this).hasClass('prev')) {
            requestedPage = parseInt($('.woocommerce-pagination .current').data('page')) - 1;
        } else {
            requestedPage = parseInt($(this).data('page'));
        }
        if (requestedPage && !isNaN(requestedPage)) {
            performSearch(requestedPage);
        }
    });

    $('.categories_list').on('click', 'li.comic-seller-cat a', function(e) {
        e.preventDefault();

        $('.categories_list a').removeClass('active');
        $(this).addClass('active');
        performSearch(1);
    });

    function selectedOrderby() {
        var selectedOption = $('.nice-select.orderby .option.selected');
        $('.nice-select.orderby .current').text(selectedOption.text()).data('value', selectedOption.data('value'));
    }

    // Not needed for now.
    function bindPaginationListeners() {
        $('.woocommerce-pagination .page-numbers').on('click', function(e) {
            e.preventDefault();
            var requestedPage;
            if ($(this).hasClass('next')) {
                requestedPage = parseInt($('.woocommerce-pagination .current').data('page')) + 1;
            } else if ($(this).hasClass('prev')) {
                requestedPage = parseInt($('.woocommerce-pagination .current').data('page')) - 1;
            } else {
                requestedPage = parseInt($(this).data('page'));
            }
            if (requestedPage && !isNaN(requestedPage)) {
                performSearch(requestedPage);
            }
        });
    }

    // Toggle auction options based on the state of the switch
    function toggleAuctionOptions() {
        var auctionsOnly = $('#auctionsonly').hasClass('switch-on');
        if (auctionsOnly) {
            $('.nice-select.orderby ul li[data-value="auction_started"], .nice-select.orderby ul li[data-value="auction_end"]').css('display', 'block');
        } else {
            $('.nice-select.orderby ul li[data-value="auction_started"], .nice-select.orderby ul li[data-value="auction_end"]').css('display', 'none');
        }
    }

    // Load saved filters from URL
    function loadSavedFilters() {
        var savedQuery = getUrlParameter('s');
        var savedCategory = getUrlParameter('product_cat');
        var savedAuctionsOnly = getUrlParameter('auctions_only');
        var savedOrderBy = getUrlParameter('orderby');
        var savedPaged = getUrlParameter('cur_page');

        if (savedQuery) {
            $('.search-keyword').val(savedQuery);
        }
        if (savedCategory) {
            $('.nice-select.form-control1 .option[data-value="' + savedCategory + '"]').addClass('selected').siblings().removeClass('selected');
            //$('.nice-select.form-control1 .current').text($('.nice-select.form-control1 .option.selected').text());
        }
        if (savedAuctionsOnly === '1') {
            $('#auctionsonly').addClass('switch-on').removeClass('switch-off');
        }
        if (savedOrderBy) {
            $('.nice-select.orderby .option[data-value="' + savedOrderBy + '"]').addClass('selected').siblings().removeClass('selected');
            $('.nice-select.orderby .current').text($('.nice-select.orderby .option.selected').text()).data('value', savedOrderBy);
        }

        // Load saved attribute filters
        $('.product-attribute-filter').each(function() {
            var name = $(this).find('input:first, select:first').attr('name').replace('[]', '');
            var savedValues = getUrlParameter(name);
            if (savedValues) {
                if ($(this).find('select').length) {
                    // If it's a select element (e.g., grade filter)
                    $(this).find('select').val(savedValues);
                } else {
                    // If it's a checkbox or radio input
                    var values = savedValues.split(',');
                    $(this).find('input').each(function() {
                        if (values.includes($(this).val())) {
                            $(this).prop('checked', true);
                            $(this).parent().addClass('checked');
                        }
                    });
                }
            }
        });

        // Load saved category filter
        var savedCategory = getUrlParameter('product_cat');
        if (savedCategory) {
            $('.product-category-filter input[value="' + savedCategory + '"]').prop('checked', true).parent().addClass('checked');
        }

        /*if(savedQuery || savedCategory || savedAuctionsOnly == 1 || savedOrderBy) {
            performSearch(1);    
        }*/

        if (savedPaged) {
            performSearch(savedPaged);
        } else {
            performSearch(1);
        }
    }

    // Check if rel_search is present in URL before calling loadSavedFilters
    if (getUrlParameter('rel_method')) {
        loadSavedFilters();
    }
});