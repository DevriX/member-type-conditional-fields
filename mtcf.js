jQuery(document).ready(function($){
	MTCF = MTCF || {};
	if ( !MTCF.type_field )
		return;
	MTCF.toggle = function() {
		if ( !MTCF.current_user_type )
			return;
		for ( var field in MTCF ) {
			if ( MTCF.hasOwnProperty(field) && 0 === field.indexOf('field_') ) {
				var y = MTCF[field].y
				  , n = MTCF[field].n
				  , t = MTCF.current_user_type
				  , elem = $('[name="'+field+'"]')
				  , cont;
				if ( elem.closest('.bp-profile-field').length ) {
					cont = elem.closest('.bp-profile-field');
				} else if ( elem.closest('.editfield').length ) {
					cont = elem.closest('.editfield');
				}
				if ( elem.length > 0 && cont.length ) {
					if ( y.indexOf(t) > -1 ) {
						cont.fadeIn(100);
					}
					else if ( n.indexOf(t) > -1 ) {
						cont.fadeOut(100);
					}
				}
			}
		}
	}
	var fieldChange = function(elem) {
		var v = $(elem).val();
		if ( !$.trim(v) ) {
			v = 'type_none';
		}
		MTCF.current_user_type = v;
		MTCF.toggle();
	}
	$(document).on('change', '.editfield [name="'+MTCF.type_field+'"]', function(e){
		return fieldChange(this);
	});
	$(document).on('change', '.bp-profile-field [name="'+MTCF.type_field+'"]', function(e){
		return fieldChange(this);
	});
	$('.editfield [name="'+MTCF.type_field+'"]').change();
	$('.bp-profile-field [name="'+MTCF.type_field+'"]').change();
	MTCF.toggle();
});