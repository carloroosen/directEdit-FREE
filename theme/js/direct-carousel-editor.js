
// re-init the carousel after editing
function initCarouselReload(listEditor, activeItem) {
	var sliderOld, sliderNew, listOld, length;

	// the only way to reset the slider is to rebuild the html
	sliderOld = listEditor.element;
	sliderID = sliderOld.attr('id');
	sliderOld.removeAttr('id'); 
	sliderNew = jQuery('<div>').attr('id', sliderID).addClass('carousel slide').attr('data-ride', 'carousel').insertAfter(sliderOld);
	// copy the content because it may contain editable elements like texts and images
	listOld = listEditor.list;
	if (activeItem != undefined) {
		listOld.children().removeClass('active');
		listOld.children().eq(activeItem).addClass('active');
	}
	indicators = jQuery('<ol>').addClass('carousel-indicators').appendTo(sliderNew);
	length = listOld.contents().length;
	for (var i = 0; i < length ; i++) {
		indicator = jQuery('<li>').attr('data-target', sliderID).attr('data-slide-to', i).appendTo(indicators);
		if (i == activeItem) {
			indicator.addClass('active');
		}
	}
	jQuery('<div>').addClass('carousel-inner').appendTo(sliderNew).append(listOld.contents());
	if (length > 0) {
		jQuery('<a>').addClass('left carousel-control').attr('href', '#'+sliderID).attr('data-slide', 'prev').appendTo(sliderNew).append(jQuery('<span>').addClass('glyphicon glyphicon-chevron-left'));
		jQuery('<a>').addClass('right carousel-control').attr('href', '#'+sliderID).attr('data-slide', 'next').appendTo(sliderNew).append(jQuery('<span>').addClass('glyphicon glyphicon-chevron-right'));
	}	
	// Run holder.js again
	Holder.run();
	sliderOld.remove();
	listEditor.element = sliderNew;
	// now create list editor buttons
	listEditor._init();
	// (carousel will init itself)
}
