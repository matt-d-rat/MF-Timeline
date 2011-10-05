$(document).ready(function(){
	$('.timeline .timeline_nav').stickyfloat({duration: 500});
	
	$('.timeline .section').each(function(){
		$(this).afterScroll(function(){
			// After we have scolled past the top
			var year = $(this).attr('id');
			$('ol.timeline_nav li').removeClass('current');
			$('ol.timeline_nav li#menu_year_' + year).addClass('current');
		});
	});
});