$(function() {
	$('#midi_key, #midi_scale').on('change',function() {
		var 
			key = $('#midi_key').val(), 
			scale = $('#midi_scale').val()
		;
		if (key && scale) {
			$.post('',{key:key,scale:scale, ajax:true},function(allowed_notes) {
				var $allNotes = $('#midi_allowed_notes').empty();
			
				for(var idx in allowed_notes) 
					$('<option />')
						.val(idx)
						.text(allowed_notes[idx])
						.appendTo($allNotes);
				
				$allNotes.closest('label').show();
				
			},'json')
		}
	})
})