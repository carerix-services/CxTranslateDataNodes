CxTranslateDataNodes
====================

This app can be used to translate the table-items of a Carerix app. To do this,
you'll need your app's XML password and a translation matrix in `.csv` format,
which should be UTF-8 encoded and look like this:

	English;Dutch;French
	per hour;per uur;par heure
	English;Engels;Anglais
	"This is ""Quoted""";"Dit staat tussen ""quotes""";"Je ne parle pas Français"

etc. Each row indicates a list of translations given for it's header row's 
language.

Other than that, the interface should be clear enough to find your way around
the interactions