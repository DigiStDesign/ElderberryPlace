<?php
require_once('includes/layout.php');
require_once('includes/page_functions.php'); #you can either embed functions into the page or move them to a separate file (helps keep things manageable/readable) and call them here. 

renderHeader("Aged Care Management System - Example Page"); #this is calling the function renderHeader() defined in includes/layout.php. This inserts the header, including navigation and style.css into all pages.

<p>This is an example page.</p> #insert HTML into the body

<?php
renderFooter(); #calls renderFooter() defined in includes/layout.php. Ensures a consistent site footer across all pages.  