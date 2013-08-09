window.PP_CSS = {

	editor_instance : null,

	render_editor : function( editor_id, editor_field_id ) {

		var editor = ace.edit( editor_id, editor_field_id );
		editor.setTheme("ace/theme/twilight");
		editor.getSession().setMode("ace/mode/css");

		var pp_css_input = jQuery("#"+ editor_field_id);
		editor.setValue( PP_CSS.decode_html( pp_css_input.val() ) );

		editor.getSession().on("change", function(e) {
			pp_css_input.val( PP_CSS.encode_html( editor.getValue() ) );
		});

		PP_CSS.editor_instance = editor;

	},

	encode_html : function( s ) {

		return jQuery("<div/>").text(s).html();

	},

	decode_html : function( s ) {

		return jQuery("<div/>").html(s).text();

	}

};