$(function() { $('head').append('<link rel="stylesheet" href="/css/debug.css" type="text/css" />'); });

var debug_toolbar = $("#debug_toolbar");

if (!$.cookie('debug_toolbar')) {
	debug_toolbar.hide();
}

$("#debug_openbutton").bind("click", function() { 
	debug_toolbar.slideToggle(250); 
	
	if ($.cookie('debug_toolbar')) {
		$.cookie('debug_toolbar', null, { path: '/', expires: 0});
	} else {
		$.cookie('debug_toolbar', 1, { path: '/', expires: 0});
	}
});

debug_toolbar.find('.options li').each(function() {
	var $this = $(this);
	if ($.cookie(this.id)) {
		$this.addClass('on');
	}
	
	$this.bind('click', function() {
		var $this = $(this);
		
		if ($this.hasClass('on')) {
			$.cookie(this.id, null, { path: '/', expires: 0});
			$this.removeClass('on');
		} else {
			$.cookie(this.id, 1, { path: '/', expires: 0});
			$this.addClass('on');
		}
	})
});

$('.infoWindowLink').bind('click', function() {
	console.log(this.rel);
	$('#'+this.rel).toggle(300);
	return false;
});

$('#db_queries .showquery').bind('click', function() {
	$(this).hide().siblings('.query').slideDown(300);
	return false;
});

