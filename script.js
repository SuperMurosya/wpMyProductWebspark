jQuery(document).ready(function($) {
    $('#upload_image_button').click(function(e) {
        e.preventDefault();
        var custom_uploader = wp.media({
            title: 'Обрати зображення',
            button: { text: 'Вибрати' },
            multiple: false
        }).on('select', function() {
            var attachment = custom_uploader.state().get('selection').first().toJSON();
            $('#product_image').val(attachment.id);
            $('#image_preview').html('<img src="' + attachment.url + '" style="max-width: 200px; height: auto;" />');
        }).open();
    });
});