// helpers.php
function isActivePage($page, $exact = false) {
    $current_uri = $_SERVER['REQUEST_URI'];
    $current_page = basename($_SERVER['PHP_SELF']);
    
    if ($exact) {
        return $current_page == $page;
    }
    
    // For dashboard specifically
    if ($page == 'index.php') {
        return ($current_page == 'index.php' && 
               strpos($current_uri, 'users/') === false &&
               strpos($current_uri, 'products/') === false &&
               strpos($current_uri, 'sales/') === false &&
               strpos($current_uri, 'reports/') === false &&
               strpos($current_uri, 'inventory/') === false &&
               strpos($current_uri, 'shifts/') === false);
    }
    
    // For directory-based pages
    return strpos($current_uri, $page) !== false;
}
