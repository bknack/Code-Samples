These files constitute a plugin developed for WordPress:

_**Please note**_ that I have developed a consistant style in writing code. I use this style across languages and markup.

I'm aware that my style deviates from the WordPress standard. I would be happy to conform to that (or another) standard in lieu of my own.

I was provided with a set of product.csv and stores.csv fiels. I took all the products for each store and merged them into WordPress so that each product had the appropriate address and longitude and latitude for each store it was in.

The data in WP had to conform to the requirements for the WP Store Locator plugin so it could be used to search for particular products in a given area.

I refactored the two helper files. In addition to formatting changes, I tightened them up so more methods and variables were private.

The product-locations-csv-importer started out as a module for a different project. It has been completely rewritten. 

It now makes heavier use of its helper functions. In particular, they encapsulate much of the work using private functions and data structures.

This has the dual benefit of consolidating the code in one place and making the use of the objects much cleaner by reducing their paramater list.