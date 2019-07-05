These files are part of one Drupal project. I've replicated the directory structure:

_**Please note**_ that I have developed a consistant style in writing code. I use this style across languages and markup.

I'm aware that my style deviates from the Drupal standard. I would be happy to conform to that (or another) standard in lieu of my own.

I was provided with clean HTML and, in particular, *.css files. The challenge was to fit
these *.css files to Drupal's HTML.

I did this using a combination of JavaScript and *.twig files. This allowed the *.css files 
to function exactly as the client intended.

*.yml files (new compared to Drupal 6)...
themes/custom/sitename/sitename.info.yml
themes/custom/sitename/sitename.libraries.yml

*.twig files (VERY new compared to how this was handled in Drupal 5 and 6 - I like this better)...
themes/custom/sitename/templates/field/field--node--field-home-slider-images--home-page.html.twig
themes/custom/sitename/templates/navigation/menu.html.twig

In addition to the theme work, the project required more than one targeted module to be
developed. The cart module allows the client to easily add items to a cart from a search page.

The PDF module allows the client to produce a PDF from a list of items that arrose from a 
facetted search.
