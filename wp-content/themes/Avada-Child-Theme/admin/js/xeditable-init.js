jQuery(document).ready(function ($) {
    jQuery('.editable').editable({
        url: function(params) {
            return $.ajax({
                url: xeditable_ajax.ajax_url,
                type: 'POST',
                dataType: 'json',
                data: {
                    action: 'update_testdetails',
                    security: xeditable_ajax.nonce,
                    item_id: jQuery(this).data('itemid'),
                    item_type: jQuery(this).data('item_type'),
                    sub_key: jQuery(this).data('subkey'),
                    key: jQuery(this).data('pk'),
                    meta_value: params.value,
                },
                success: function(response) {
                    if (response.success) {
                        console.log(response.data.message);
                    } else {
                        console.error(response.data.message);
                    }
                }
            });
        },

        mode: 'inline', // Inline editing
        success: function (response) {
            if (!response.success) {
              return response.data.message;
            }
        },
        error: function () {
            return 'Error updating value.';
        },
    });
});
