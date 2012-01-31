$('head').append('<link rel="stylesheet" href="/css/debug.css" type="text/css" />');

setTimeout(function() {
	var toolbar = $("#jmvc-debug-toolbar");
	var toolbar_height = toolbar.height();

	if (!$.cookie('jmvc-debug-toolbar')) {
		toolbar.hide();
	} else {
		$('body').css('margin-bottom', toolbar_height+50);
	}

	$(".jmvc-debug-toggle").bind("click", function() {

		if (toolbar.is(':hidden')) {
			toolbar.slideDown(200);
			$('body').css('margin-bottom', toolbar_height);
			$.cookie('jmvc-debug-toolbar', 1, { path: '/', expires: 0});
		} else {
			toolbar.slideUp(200);
			$('#jmvc-debug-infoWindows').hide();
			$('body').css('margin-bottom', 0);
			$.cookie('jmvc-debug-toolbar', null, { path: '/', expires: 0});
		}
	});

	toolbar.find('.jmvc-debug-toggle-option').each(function() {
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

	$('.jmvc-debug-infoWindowLink').bind('click', function() {
		console.log(this.rel);
		$('#'+this.rel).toggle(200);
		return false;
	});

	$('#jmvc-debug-dbqueries .showquery').bind('click', function() {
		$(this).hide().siblings('.query').slideDown(200);
		return false;
	});

}, 1000);