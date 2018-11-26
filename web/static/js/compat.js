// Check of browser can use object-fill (IE and Edge can't)
if ('objectFit' in document.documentElement.style === false) {
    // use fallback inline style background-image (no responsiveness)
    var objeftFitElements = document.getElementsByClassName('__object-fit-poly');
    if (objeftFitElements.length > 0) {
        for (var i = 0; i < objeftFitElements.length; i++) {
            var imageUrl = objeftFitElements[i].dataset.iefix;
            objeftFitElements[i].classList.add('__no-object-fill');
            objeftFitElements[i].style.backgroundImage = 'url('+imageUrl+');';   
        }
    }
}