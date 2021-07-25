
(function($){
    $().ready(function() {
        $('.ce_serviceLink  .service-link-icon').on('click', function(event){
            event.preventDefault();
            event.stopPropagation();
            var link = $(this).closest('.ce_serviceLink').find('a');
            if(link.prop('href')){
                window.location.href = link.prop('href');
            }
        });
        $('.serviceLink').on('mousenter', function(event){
            this.css('cursor', 'pointer');
            event.preventDefault();
            event.stopPropagation();
        });
    });
})(jQuery);
