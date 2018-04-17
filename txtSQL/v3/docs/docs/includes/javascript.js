function ClipBoard ( area )
{
	holdtext.innerText = area.innerText;
	Copied             = holdtext.createTextRange();

	if ( Copied.execCommand("Copy") == true )
	{
		alert('Text copied to clipboard successfully');
	}
}

function openCommentForm ( funcID )
{
	var w   = 500, h = 500;
	var top = ( screen.height - h ) / 2 - 15, left = ( screen.width - w ) / 2;

	if ( top < 0 )
	{
		top = 0;
	}

	if ( left < 0 )
	{
		left = 0;
	}

	window.open('index.php?page=comment&f=' + funcID,
	            'AddNote',
	            'width=' + w + ',height=' + h + ',top=' + top + ', left=' + left + ',scrollbars=yes');
}