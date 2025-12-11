<?php
if (!defined('WP_DEBUG')) {
  die('Direct access forbidden.');
}
add_action('wp_enqueue_scripts', function () {
  wp_enqueue_style('parent-style', get_template_directory_uri() . '/style.css');
});

// Change meta title for Property archive
add_filter('wpseo_title', 'custom_property_archive_title');
function custom_property_archive_title($title)
{
  if (is_post_type_archive('property')) {
    $title = 'Explore Properties in Japan | Buy, Rent & Invest with Mr. LAND';
  }
  return $title;
}

// Change meta description for Property archive
add_filter('wpseo_metadesc', 'custom_property_archive_description');
function custom_property_archive_description($description)
{
  if (is_post_type_archive('property')) {
    $description = 'Find top properties in Japan, from homes to investments. Buy, rent, or invest with expert guidance from Mr. LAND';
  }
  return $description;
}

/*
START Single Property functions.
*/

function format_number_with_commas($number, $decimals = 0)
{
  // Handle empty or null values
  if (empty($number) || !is_numeric($number)) {
    return "0"; // Return a default value
  }

  return number_format((float) $number, $decimals);
}

function acf_mrl_shortcode($atts)
{
  $atts = shortcode_atts(
    array(
      'field' => '', // Field name
      'post_id' => get_the_ID() // Current post ID
    ),
    $atts,
    'acf'
  );

  if (!$atts['field'])
    return '';

  return get_field($atts['field'], $atts['post_id']);
}
add_shortcode('acf', 'acf_mrl_shortcode');

function property_details_header_shortcode($atts)
{
  $atts = shortcode_atts(array('lang' => 'en'), $atts);

  $title = get_field('general_title');
  $sub_title = get_field('general_sub_title');
  $property_type = get_field('general_property_type');
  $property_type_ja = get_field('general_property_type_ja');
  $price = get_field('general_price');
  $layout = get_field('general_layout');

  ob_start();
  $prefix = "";
  $decimals = 0;
  $price_format = "";

  if ($price) {

    // Determine price prefix based on taxonomy
    // Assume the post has a location-type term assigned
    $terms = wp_get_post_terms(get_the_ID(), 'location-type', array('fields' => 'slugs'));

    $is_abroad = false;

    if (!empty($terms)) {
      foreach ($terms as $term_slug) {
        if (is_under_abroad('location-type', $term_slug)) {
          $is_abroad = true;
          break; // one is enough
        }
      }
    }

    if ($is_abroad) {
      $prefix = "$ ";
    } else {
      $prefix = "¥ ";
    }

    // Format price
    $decimals = 0;
    $price_format = format_number_with_commas($price, $decimals);
  }
  $selected_currency = $is_abroad ? 'USD' : 'JPY';


  echo '<div class="property-header-container">';

  if (esc_attr($atts['lang']) == 'en') {
    $property_type_label = get_property_type_label($property_type, 'en');
    echo '<a class="mrl-link mrl-link-black" href="/properties">';
    echo '<div class="property-header-label">JAPAN ' . esc_html($property_type_label) . ' FOR SALE </div>';
    echo '</a>';
  } else {
    $property_type_label = get_property_type_label($property_type_ja, 'ja');
    echo '<a class="mrl-link mrl-link-black" href="/jp/properties-jp">';
    echo '<div class="property-header-label">日本の' . esc_html($property_type_label) . '販売</div>';
    echo '</a>';
  }

  echo '<div class="property-header-sub-title">' . esc_html($sub_title) . '</div>';
  echo '<div class="property-header-title">' . esc_html($title) . '</div>';

  echo '<div class="property-header-price-layout-wrapper">';
  echo '<div class="property-header-layout">' . esc_html($layout) . '</div>';

  echo '<div class="property-header-price" style="display: flex; align-items: center; gap: 10px;">';
  echo '<span id="converted-price">' . $prefix . esc_html($price_format) . '</span>';

  echo '</div>'; // .property-header-price
  echo '<div class="property-header-currency">';
  echo '<select id="currency-selector">
    <option value="JPY"' . ($selected_currency === 'JPY' ? ' selected' : '') . '>JPY</option>
    <option value="USD"' . ($selected_currency === 'USD' ? ' selected' : '') . '>USD</option>
    <option value="EUR"' . ($selected_currency === 'EUR' ? ' selected' : '') . '>EUR</option>
    <option value="CNY"' . ($selected_currency === 'CNY' ? ' selected' : '') . '>CNY</option>
    <option value="CAD"' . ($selected_currency === 'CAD' ? ' selected' : '') . '>CAD</option>
    <option value="AUD"' . ($selected_currency === 'AUD' ? ' selected' : '') . '>AUD</option>
    </select>';
  echo '</div>'; // .property-header-currency

  echo '</div>'; // .property-header-price-layout-wrapper
  echo '</div>'; // .property-header-container

  // Output base price for JS
  echo '<script>
		const basePrice = ' . intval($price) . ';
    	const baseCurrency = "' . $selected_currency . '";
    </script>';

  return ob_get_clean();
}

add_shortcode('property_details_header', 'property_details_header_shortcode');

function property_details_about_shortcode()
{
  $about = get_field('general_about');

  ob_start();

  if ($about) {
    echo '<div class="property-about">';
    echo $about;
    echo '</div>';
  } else {
    echo 'No Record found';
  }


  return ob_get_clean();
}

add_shortcode('property_details_about', 'property_details_about_shortcode');

function property_details_location_shortcode()
{
  $location = get_field('general_location');

  ob_start();

  if ($location) {
    echo $location;
  } else {
    echo 'No Record found';
  }


  return ob_get_clean();
}

add_shortcode('property_details_location', 'property_details_location_shortcode');

function enqueue_currency_converter_script()
{
  if (is_singular()) {
    ?>
    <script>
      document.addEventListener('DOMContentLoaded', function () {
        const selector = document.getElementById('currency-selector');
        const priceElement = document.getElementById('converted-price');

        // Parse initial price and currency from the DOM
        let initialText = priceElement.textContent.trim();
        let basePrice = parseFloat(initialText.replace(/[^0-9.]/g, '')); // removes currency symbols
        let currentCurrency = initialText.includes('¥') ? 'JPY' : 'USD'; // detect initial currency (adjust as needed)

        selector.addEventListener('change', async function () {
          const targetCurrency = this.value;

          // If target is same as current, just display base price
          if (targetCurrency === currentCurrency) {
            if (targetCurrency === 'JPY') {
              priceElement.textContent = '¥ ' + basePrice.toLocaleString();
            } else {
              priceElement.textContent = new Intl.NumberFormat('en-US', { style: 'currency', currency: targetCurrency }).format(basePrice);
            }
            return;
          }

          // Fetch conversion from currentCurrency → targetCurrency
          const url = `https://api.frankfurter.app/latest?amount=${basePrice}&from=${currentCurrency}&to=${targetCurrency}`;

          try {
            const response = await fetch(url);
            const data = await response.json();
            const converted = data.rates[targetCurrency];

            // Format and display
            const formatted = new Intl.NumberFormat('en-US', { style: 'currency', currency: targetCurrency }).format(converted);
            priceElement.textContent = formatted;

            // Update current base for next conversion
            currentCurrency = targetCurrency;
            basePrice = converted;
          } catch (error) {
            console.error('Currency conversion error:', error);
            priceElement.textContent = 'Conversion failed';
          }
        });
      });
    </script>

    <?php
  }
}
add_action('wp_footer', 'enqueue_currency_converter_script');

function format_date_to_japanese_year_month($date_string)
{

  // Extract month and year from the Japanese-style date
  if (preg_match('/(\d+)月\s*(\d{4})/', $date_string, $matches)) {
    $month = $matches[1];
    $year = $matches[2];

    // Create a date string in a format strtotime() can parse
    $formatted_date = $year . '-' . str_pad($month, 2, '0', STR_PAD_LEFT) . '-01';
    $timestamp = strtotime($formatted_date);


    if ($timestamp === false) {
      return '';
    }

    return date('Y年m月', $timestamp);
  } else {
    // Failed to parse
    return '';
  }
}


function format_acf_value($field_key, $value, $lang)
{
  $prefix = '';
  $suffix = '';

  switch ($field_key) {
    case 'general_total_floor_size':
    case 'general_land_size':
    case 'general_floor_area':
    case 'general_building_area':
    case 'general_land_area':
      $suffix = 'm²';
      break;

    case 'general_floor_area_ratio':
    case 'general_building_to_land_ratio':
    case 'general_gross_yield':
      $suffix = '%';
      break;

    case 'general_price':
    case 'general_management_fee':
    case 'general_repair_reserve_fund_fee':
    case 'general_monthly_rental_income':
    case 'general_yearly_rental_income':
      $prefix = '¥ ';
      $value = format_number_with_commas($value, 0);
      break;

    case 'general_completion_date':
      if ($lang !== 'en') {
        $value = format_date_to_japanese_year_month($value);
      }
      break;
  }

  return [$prefix, $value, $suffix];
}

function get_property_type_label($key, $lang = 'en')
{
  $labels = [
    'en' => [
      'holidayHome' => 'Holiday home',
      'house' => 'House',
      'condo' => 'Condo',
      'investPropertyWholeBldg' => 'Investment Property (Whole Bldg)',
      'investPropertyCondo' => 'Investment Property (Condo)',
    ],
    'ja' => [
      'holidayHome' => '別荘',
      'house' => '戸建',
      'condo' => 'マンション',
      'investPropertyWholeBldg' => '投資用不動産（1棟）',
      'investPropertyCondo' => '投資用不動産（区分マンション）',
    ],
  ];

  if (isset($labels[$lang][$key])) {
    return $labels[$lang][$key];
  }

  return ''; // default empty if no match
}

function get_property_label($property_type, $lang = 'en')
{
  $label_map = [
    'holidayHome' => [
      'en' => [
        'general_property_type' => 'Property Type',
        'general_price' => 'Price',
        'general_location' => 'Location',
        'general_access' => 'Access',
        'general_urban_planning_area' => 'Urban Planning Area',
        'general_land_classification' => 'Land Category',
        'general_zoning' => 'Zoning',
        'general_rights' => 'Land Rights',
        'general_building_area' => 'Building Area',
        'general_land_size' => 'Land Size',
        'general_building_to_land_ratio' => 'Building to Land Ratio',
        'general_floor_area_ratio' => 'Floor Area Ratio',
        'general_structure' => 'Structure',
        'general_completion_date' => 'Completion Date',
        'general_layout' => 'Layout',
        'general_parking' => 'Parking',
        'general_status' => 'Status',
        'general_handover_date' => 'Handover Date',
      ],
      'ja' => [
        'general_property_type_ja' => '物件種類',
        'general_price' => '価格',
        'general_location' => '所在地',
        'general_access' => '最寄り駅からのアクセス',
        'general_urban_planning_area_ja' => '都市計画',
        'general_land_classification_ja' => '地目',
        'general_zoning_ja' => '用途地域',
        'general_rights_ja' => '所有権',
        'general_building_area' => '建物面積',
        'general_land_size' => '土地面積',
        'general_building_to_land_ratio' => '建蔽率',
        'general_floor_area_ratio' => '容積率',
        'general_structure_ja' => '構造',
        'general_completion_date' => '築年月',
        'general_layout' => '間取り',
        'general_parking_ja' => '駐車場',
        'general_status_ja' => '現況',
        'general_handover_date_ja' => '引き渡し日',
      ],
    ],
    'house' => [
      'en' => [
        'general_property_type' => 'Property Type',
        'general_price' => 'Price',
        'general_location' => 'Location',
        'general_access' => 'Access',
        'general_urban_planning_area' => 'Urban Planning Area',
        'general_land_classification' => 'Land Category',
        'general_zoning' => 'Zoning',
        'general_rights' => 'Land Rights',
        'general_building_area' => 'Building Area',
        'general_land_size' => 'Land Size',
        'general_building_to_land_ratio' => 'Building to Land Ratio',
        'general_floor_area_ratio' => 'Floor Area Ratio',
        'general_structure' => 'Structure',
        'general_completion_date' => 'Completion Date',
        'general_layout' => 'Layout',
        'general_parking' => 'Parking',
        'general_status' => 'Status',
        'general_handover_date' => 'Handover Date',
      ],
      'ja' => [
        'general_property_type_ja' => '物件種類',
        'general_price' => '価格',
        'general_location' => '所在地',
        'general_access' => '最寄り駅からのアクセス',
        'general_urban_planning_area_ja' => '都市計画',
        'general_land_classification_ja' => '地目',
        'general_zoning_ja' => '用途地域',
        'general_rights_ja' => '所有権',
        'general_building_area' => '建物面積',
        'general_land_size' => '土地面積',
        'general_building_to_land_ratio' => '建蔽率',
        'general_floor_area_ratio' => '容積率',
        'general_structure_ja' => '構造',
        'general_completion_date' => '築年月',
        'general_layout' => '間取り',
        'general_parking_ja' => '駐車場',
        'general_status_ja' => '現況',
        'general_handover_date_ja' => '引き渡し日',
      ],
    ],
    'condo' => [
      'en' => [
        'general_property_type' => 'Property Type',
        'general_price' => 'Price',
        'general_location' => 'Location',
        'general_access' => 'Access',
        'general_zoning' => 'Zoning',
        'general_rights' => 'Land Rights',
        'general_building_name' => 'Building Name',
        'general_floor_area' => 'Floor Area',
        'general_floor_located' => 'Floor Located',
        'general_structure' => 'Structure',
        'general_completion_date' => 'Completion Date',
        'general_layout' => 'Layout',
        'general_parking' => 'Parking',
        'general_management_type' => 'Management Type',
        'general_management_fee' => 'Management Fee',
        'general_repair_reserve_fund_fee' => 'Repair Reserve Fund Fee',
        'general_status' => 'Status',
        'general_handover_date' => 'Handover Date',
      ],
      'ja' => [
        'general_property_type_ja' => '物件種類',
        'general_price' => '価格',
        'general_location' => '所在地',
        'general_access' => '最寄り駅からのアクセス',
        'general_zoning_ja' => '用途地域',
        'general_rights_ja' => '所有権',
        'general_building_name' => '建物名',
        'general_floor_area' => 'フロア面積',
        'general_floor_located' => '物件の所在階数',
        'general_structure_ja' => '構造',
        'general_completion_date' => '築年月',
        'general_layout' => '間取り',
        'general_parking_ja' => '駐車場',
        'general_management_type_ja' => '管理形態',
        'general_management_fee' => '管理費',
        'general_repair_reserve_fund_fee' => '修繕積立費',
        'general_status_ja' => '現況',
        'general_handover_date_ja' => '引き渡し日',
      ],
    ],
    'investPropertyWholeBldg' => [
      'en' => [
        'general_property_type' => 'Property Type',
        'general_price' => 'Price',
        'general_location' => 'Location',
        'general_access' => 'Access',
        'general_urban_planning_area' => 'Urban Planning Area',
        'general_land_classification' => 'Land Category',
        'general_zoning' => 'Zoning',
        'general_rights' => 'Land Rights',
        'general_building_name' => 'Building Name',
        'general_num_of_floor' => '# of Floors',
        'general_land_area' => 'Land Area',
        'general_total_floor_size' => 'Total Floor Size',
        'general_total_units' => 'Total Units',
        'general_building_to_land_ratio' => 'Building to Land Ratio',
        'general_floor_area_ratio' => 'Floor Area Ratio',
        'general_structure' => 'Structure',
        'general_completion_date' => 'Completion Date',
        'general_gross_yield' => 'Gross Yield',
        'general_monthly_rental_income' => 'Monthly Rental Income',
        'general_yearly_rental_income' => 'Yearly Rental Income',
        'general_status' => 'Status',
        'general_handover_date' => 'Handover Date',
      ],
      'ja' => [
        'general_property_type_ja' => '物件種類',
        'general_price' => '価格',
        'general_location' => '所在地',
        'general_access' => '最寄り駅からのアクセス',
        'general_urban_planning_area_ja' => '都市計画',
        'general_land_classification_ja' => '地目',
        'general_zoning_ja' => '用途地域',
        'general_rights_ja' => '所有権',
        'general_building_name' => '建物名',
        'general_num_of_floor' => '階数',
        'general_land_area' => '土地面積',
        'general_total_floor_size' => '延床面積',
        'general_total_units' => '合計部屋数',
        'general_building_to_land_ratio' => '建蔽率',
        'general_floor_area_ratio' => '容積率',
        'general_structure_ja' => '構造',
        'general_completion_date' => '築年月',
        'general_gross_yield' => '表面利回り',
        'general_monthly_rental_income' => '満室想定月収',
        'general_yearly_rental_income' => '満室想定年収',
        'general_status_ja' => '現況',
        'general_handover_date_ja' => '引き渡し日',


      ],
    ],
    'investPropertyCondo' => [
      'en' => [
        'general_property_type' => 'Property Type',
        'general_price' => 'Price',
        'general_location' => 'Location',
        'general_access' => 'Access',
        'general_urban_planning_area' => 'Urban Planning Area',
        'general_land_classification' => 'Land Category',
        'general_zoning' => 'Zoning',
        'general_rights' => 'Land Rights',
        'general_building_name' => 'Building Name',
        'general_num_of_floor' => '# of Floors',
        'general_floor_located' => 'Floor Located',
        'general_building_area' => 'Building Area',
        'general_total_floor_size' => 'Total Floor Size',
        'general_parking' => 'Parking',
        'general_management_type' => 'Management Type',
        'general_structure' => 'Structure',
        'general_completion_date' => 'Completion Date',
        'general_gross_yield' => 'Gross Yield',
        'general_management_fee' => 'Management Fee',
        'general_repair_reserve_fund_fee' => 'Repair Reserve Fund Fee',
        'general_status' => 'Status',
        'general_handover_date' => 'Handover Date',
      ],
      'ja' => [
        'general_property_type_ja' => '物件種類',
        'general_price' => '価格',
        'general_location' => '所在地',
        'general_access' => '最寄り駅からのアクセス',
        'general_urban_planning_area_ja' => '都市計画',
        'general_land_classification_ja' => '地目',
        'general_zoning_ja' => '用途地域',
        'general_rights_ja' => '所有権',
        'general_building_name' => '建物名',
        'general_num_of_floor' => '階数',
        'general_floor_located' => '物件の所在階数',
        'general_building_area' => '建物面積',
        'general_total_floor_size' => '延床面積',
        'general_parking_ja' => '駐車場',
        'general_management_type_ja' => '管理形態',
        'general_structure_ja' => '構造',
        'general_completion_date' => '築年月',
        'general_gross_yield' => '表面利回り',
        'general_management_fee' => '管理費',
        'general_repair_reserve_fund_fee' => '修繕積立費',
        'general_status_ja' => '現況',
        'general_handover_date_ja' => '引き渡し日',


      ],
    ],
  ];

  return $label_map[$property_type][$lang] ?? [];
}


function property_details_shortcode($atts)
{
  $atts = shortcode_atts(['lang' => 'en'], $atts);
  $lang = esc_attr($atts['lang']);
  $language = get_field('general_language');
  $property_type = get_field('general_property_type');
  $property_type_ja = get_field('general_property_type_ja');
  $is_property_type = ['general_property_type', 'general_property_type_ja'];

  if ($lang === 'en' || $language === 'en') {
    $acf_fields = get_property_label($property_type, 'en');
    $property_type_label = get_property_type_label($property_type, 'en');
  } else {
    $acf_fields = get_property_label($property_type_ja, 'ja');
    $property_type_label = get_property_type_label($property_type_ja, 'ja');
  }
  // check the current post is abroad
  $post_id = get_the_ID();

  $terms = wp_get_post_terms($post_id, 'location-type', array(
    'fields' => 'all'
  ));
  $is_abroad = false;

  if (!empty($terms) && !is_wp_error($terms)) {
    foreach ($terms as $term) {
      if ($term->slug === 'abroad') {  // change 'abroad' to the actual slug if different
        $is_abroad = true;
        break; // one match is enough
      }
    }
  }
  ob_start();

  echo '<div class="property-details">';

  foreach ($acf_fields as $field_key => $label) {
    // Conditional exclusions
    $value = get_field($field_key);
    if (empty($value))
      continue;

    list($prefix, $formatted_value, $suffix) = format_acf_value($field_key, $value, $lang);

    if ($is_abroad && $field_key === 'general_price') {
      $prefix = '$';
    }

    echo '<div class="property-detail">';
    echo '<strong>' . esc_html($label) . ':</strong> ';
    if (in_array($field_key, $is_property_type, true)) {
      echo '<span>' . $property_type_label . '</span>';
    } else {
      echo '<span>' . $prefix . esc_html($formatted_value) . $suffix . '</span>';
    }

    echo '</div>';
  }

  echo '</div>';
  return ob_get_clean();
}

add_shortcode('property_details', 'property_details_shortcode');

function similar_properties_shortcode($atts)
{
  // Extract shortcode attributes
  $atts = shortcode_atts(array(
    'post_type' => 'property', // Default post type
    'posts_per_page' => 3,          // Default number of posts
    'orderby' => 'date',     // Order by date to get the latest posts first
    'order' => 'DESC',      // Display in descending order (newest first)
    'taxonomy' => '',         // Taxonomy name (e.g., 'property_type')
    'term' => '',         // Taxonomy term slug (e.g., 'condo')
    'lang' => 'en',      // Language attribute (en or ja)
  ), $atts);

  // Get the current post ID
  $current_post_id = get_the_ID();

  // Define the query arguments
  $args = array(
    'post_type' => $atts['post_type'], // Use dynamic post type
    'posts_per_page' => $atts['posts_per_page'], // Number of posts to display
    'post__not_in' => array($current_post_id), // Exclude the current property
    'orderby' => $atts['orderby'], // Randomize the results (optional)
  );

  // Add taxonomy filter if provided
  if (!empty($atts['taxonomy']) && !empty($atts['term'])) {
    $args['tax_query'] = array(
      array(
        'taxonomy' => $atts['taxonomy'], // Taxonomy name (e.g., 'property_type')
        'field' => 'slug',            // Use term slug
        'terms' => $atts['term'],     // Term slug (e.g., 'condo')
      ),
    );
  }

  // Run the query
  $query = new WP_Query($args);

  // Start the output
  $output = '<div class="similar-properties">';

  // Check if there are posts
  if ($query->have_posts()):
    while ($query->have_posts()):
      $query->the_post();
      // Get the ACF field values
      $general_layout = get_field('general_layout');
      $general_title = get_field('general_title');
      $general_price = get_field('general_price');
      $general_sub_title = get_field('general_sub_title');

      // Start the property card with a link to the property details page
      // Start the property card with a link to the property details page
      $output .= '<div class="property-card">';
      $output .= '<a href="' . get_the_permalink() . '">';

      // Display the featured image
      if (has_post_thumbnail()) {
        $output .= '<div class="property-image">';
        $output .= get_the_post_thumbnail(get_the_ID(), 'full', array('class' => 'property-thumbnail')); // Use 'full' for the full-sized image
        $output .= '</div>';
      }

      $output .= '<div class="property-wrap">';

      // Display the general layout (if applicable)
      if ($general_layout) {
        $output .= '<div class="property-layout">' . esc_html($general_layout) . '</div>';
      }

      // Display the general title
      if ($general_title) {
        $output .= '<h3 class="property-title">' . esc_html($general_title) . '</h3>';
      }

      // Display the general price
      if ($general_price) {
        $output .= '<div class="property-price">¥ ' . esc_html(format_number_with_commas($general_price, 0)) . ' </div>';
      }

      // Display the general sub title
      if ($general_sub_title) {
        $output .= '<div class="property-sub-title">' . esc_html($general_sub_title) . '</div>';
      }
      $output .= '</a>';
      // Get inquiry button
      $output .= get_inquiry_button_html(get_the_ID(), esc_attr($atts['lang']));

      // End the property wrap
      $output .= '</div>';

      // End the property card
      $output .= '</div>';
    endwhile;
    wp_reset_postdata(); // Reset the post data
  else:
    $output .= '<div class="no-properties-container">';
    if (esc_attr($atts['lang']) == 'en') {
      $output .= '<p>No similar properties found.</p>';
    } else {
      $output .= '<p>物件は見つかりませんでした。</p>';
    }
    $output .= '</div>';

  endif;

  // End the output
  $output .= '</div>';

  return $output;
}
add_shortcode('similar_properties', 'similar_properties_shortcode');

function latest_properties_by_type_shortcode($atts)
{
  // Extract shortcode attributes
  $atts = shortcode_atts(array(
    'post_type' => 'property',
    'posts_per_page' => 3,
    'order' => 'DESC',
    'property_type' => '',   // e.g., Condo
    'lang' => 'en',
  ), $atts);

  // Get the current post ID
  $current_post_id = get_the_ID();

  // Build meta query for ACF field 'general_property_type'
  $meta_query = array();
  if (!empty($atts['property_type'])) {

    $property_types = array_map('trim', explode(',', $atts['property_type']));

    $meta_query[] = array(
      'key' => $atts['lang'] === 'en' ? 'general_property_type' : 'general_property_type_ja',
      'value' => count($property_types) > 1 ? $property_types : $property_types[0],
      'compare' => count($property_types) > 1 ? 'IN' : '='
    );
  }
  // START Add availability filter: true or null 25/10/03
  $meta_query[] = array(
    'relation' => 'OR',
    array(
      'key' => 'general_is_available',
      'value' => '1',
      'compare' => '=',
    ),
    array(
      'key' => 'general_is_available',
      'compare' => 'NOT EXISTS',
    ),
  );
  // END Add availability filter: true or null 25/10/03
  $pickedup_query = get_pickedup_properties($atts['property_type'], 'property', $atts['lang'], $atts['posts_per_page']);
  $found_pickedup_count = $pickedup_query->found_posts;


  if ($found_pickedup_count < $atts['posts_per_page']) {
    $pickedup_property_ids = wp_list_pluck($pickedup_query->posts, 'ID');

    $args = array(
      'post_type' => $atts['post_type'],
      'posts_per_page' => $atts['posts_per_page'] - count($pickedup_property_ids),
      'post__not_in' => array_merge(array($current_post_id), $pickedup_property_ids),
      'orderby' => 'date',
      'order' => $atts['order'],
      'meta_query' => $meta_query,
    );

    $query = new WP_Query($args);
    $query_post = $query->posts;
    $query = update_query_posts($query, array_merge($pickedup_query->posts, $query_post));
  } else {
    $query = $pickedup_query;
  }
  ;

  // START add get posts if queried posts is less than 3 25/10/03
  $posts = $query->posts;
  $found_count = count($posts);
  if ($found_count < $atts['posts_per_page']) {
    $remaining = $atts['posts_per_page'] - $found_count;

    // Step 3: Query unavailable posts (is_available = 0) excluding already found posts
    $unavailable_meta_query = $meta_query;
    $meta_array_counts = count($meta_query);
    if ($meta_array_counts > 1) {
      $meta_query[1] = [
        'key' => 'general_is_available',
        'value' => '0',
        'compare' => '=',
      ];
    }
    $args_unavailable = array(
      'post_type' => $atts['post_type'],
      'posts_per_page' => $remaining,
      'post__not_in' => array($current_post_id),
      'orderby' => 'date',
      'order' => $atts['order'],
      'meta_query' => $meta_query,
    );

    $unavailable_query = new WP_Query($args_unavailable);
    echo '<script>';
    echo 'console.log(' . json_encode($unavailable_query) . ');';
    echo '</script>';

    // Step 4: Merge posts
    $posts = array_merge($posts, $unavailable_query->posts);
    echo '<script>';
    echo 'console.log(' . json_encode($posts) . ');';
    echo '</script>';
  }
  $query->posts = $posts;
  $query->post_count = count($posts);
  $query->found_posts = count($posts);
  $query->max_num_pages = count($posts);

  wp_reset_postdata();

  // END add get posts if queried posts is less than 3 25/10/03

  ob_start();
  echo '<div class="latest-properties-by-type">';

  if ($query->have_posts()):
    while ($query->have_posts()):
      $query->the_post();
      $general_layout = get_field('general_layout');
      $general_title = get_field('general_title');
      $general_price = get_field('general_price');
      $general_sub_title = get_field('general_sub_title');

      echo '<div class="property-card">';
      echo '<a href="' . get_the_permalink() . '">';

      // START EDIT add class for close 25/10/03
      $general = get_field('general', get_the_ID());
      $is_available = $general['is_available'] ?? null;
      $lang = $general['language'] ?? null; // en or ja
      $class = 'property-image';

      if (has_post_thumbnail()) {
        if (!$is_available) {
          $class .= ' not_available ' . esc_attr($lang);
        }

        $post_id = get_the_ID(); // current post ID
        // Get "picked-up-property" class if applicable
        $status_class = get_property_status_class($post_id);
        if ($status_class) {
          $class .= ' ' . $status_class;
        }
        echo '<div class="' . esc_attr($class) . '"' . get_post_date_attribute($post_id) . '>';

        echo get_the_post_thumbnail(get_the_ID(), 'full', array('class' => 'property-thumbnail'));
        echo '</div>';
      }
      // END EDIT add class for close 25/10/03


      echo '<div class="property-wrap">';

      if ($general_layout) {
        echo '<div class="property-layout">' . esc_html($general_layout) . '</div>';
      }

      if ($general_title) {
        echo '<h3 class="property-title">' . esc_html($general_title) . '</h3>';
      }

      if ($general_price) {
        echo '<div class="property-price">¥ ' . esc_html(format_number_with_commas($general_price, 0)) . '</div>';
      }

      if ($general_sub_title) {
        echo '<div class="property-sub-title">' . esc_html($general_sub_title) . '</div>';
      }

      echo '</a>';

      echo get_inquiry_button_html(get_the_ID(), $atts['lang']);

      echo '</div>'; // .property-wrap
      echo '</div>'; // .property-card
    endwhile;
    wp_reset_postdata();
  else:
    echo '<div class="no-properties-container">';
    echo $atts['lang'] === 'en' ? '<p>No properties found.</p>' : '<p>物件は見つかりませんでした。</p>';
    echo '</div>';
  endif;

  echo '</div>'; // .latest-properties-by-type

  return ob_get_clean();
}
add_shortcode('latest_properties_by_type', 'latest_properties_by_type_shortcode');

function property_gallery_slideshow_shortcode()
{
  // Array of ACF gallery image fields
  $gallery_fields = [
    'images_gallery_image_1',
    'images_gallery_image_2',
    'images_gallery_image_3',
    'images_gallery_image_4',
    'images_gallery_image_5',
    'images_gallery_image_6',
    'images_gallery_image_7',
    'images_gallery_image_8',
  ];

  // Start output buffering
  ob_start();

  // Check if there are any images
  $has_images = false;
  foreach ($gallery_fields as $field) {
    if (get_field($field)) {
      $has_images = true;
      break;
    }
  }

  // Display the gallery slideshow if images exist
  if ($has_images) {
    echo '<div class="swiper-container property-gallery-slideshow">';
    echo '<div class="swiper-wrapper">';

    foreach ($gallery_fields as $field) {
      $image = get_field($field);
      if ($image) {
        echo '<div class="swiper-slide">';
        echo '<img src="' . esc_url($image['url']) . '" alt="' . esc_attr($image['alt']) . '" loading="lazy">';
        echo '</div>';
      }
    }

    echo '</div>'; // Close .swiper-wrapper

    // Add navigation buttons
    echo '<div class="custom-swiper-button-next">&#62;</div>';
    echo '<div class="custom-swiper-button-prev">&#60;</div>';

    echo '</div>'; // Close .swiper-container
  }

  // Return the output
  return ob_get_clean();
}

function floor_plan_tabs_shortcode($atts)
{
  // Extract shortcode attributes
  $atts = shortcode_atts(array(
    'lang' => 'en',      // Language attribute (en or ja)
  ), $atts);

  // Start output buffering
  ob_start();

  // Array of ACF floor plan fields
  $floor_plans = [
    'images_floor_plan_1' => '1F',
    'images_floor_plan_2' => '2F',
    'images_floor_plan_3' => '3F',
    'images_floor_plan_4' => '4F',
  ];

  // Check if any floor plan exists
  $has_floor_plans = false;
  foreach ($floor_plans as $field => $label) {
    if (get_field($field)) {
      $has_floor_plans = true;
      break;
    }
  }

  // Display the tabs if floor plans exist
  if ($has_floor_plans) {
    echo '<div class="floor-plan-tabs">';
    echo '<div class="tabs-header">';
    if (esc_attr($atts['lang']) == 'en') {
      echo '<p class="header-title">FLOORPLAN</p>';
    } else {
      echo '<p class="header-title">フロアプラン</p>';
    }
    // Tab navigation
    echo '<div class="tab-nav">';
    foreach ($floor_plans as $field => $label) {
      if (get_field($field)) {
        echo '<button class="tab-link" data-tab="' . esc_attr($field) . '">' . esc_html($label) . '</button>';
      }
    }
    echo '</div>'; // Close .tab-nav
    echo '</div>'; // Close .tabs-header

    // Tab content
    echo '<div class="tab-content">';
    foreach ($floor_plans as $field => $label) {
      $image = get_field($field);
      if ($image) {
        echo '<div id="' . esc_attr($field) . '" class="tab-pane">';
        echo '<img src="' . esc_url($image['url']) . '" alt="' . esc_attr($image['alt']) . '">';
        echo '</div>'; // Close .tab-pane
      }
    }
    echo '</div>'; // Close .tab-content

    echo '</div>'; // Close .floor-plan-tabs
  }

  // Return the output
  return ob_get_clean();
}

// Register the shortcode
add_shortcode('floor_plan_tabs', 'floor_plan_tabs_shortcode');

function custom_tabs_scripts()
{
  $script = "
    document.addEventListener('DOMContentLoaded', function() {
        const tabLinks = document.querySelectorAll('.tab-link');
        const tabPanes = document.querySelectorAll('.tab-pane');

        tabLinks.forEach((link) => {
            link.addEventListener('click', function() {
                tabLinks.forEach((link) => link.classList.remove('active'));
                tabPanes.forEach((pane) => pane.classList.remove('active'));

                const targetTab = this.getAttribute('data-tab');
                this.classList.add('active');
                document.getElementById(targetTab).classList.add('active');
            });
        });

        if (tabLinks.length > 0) {
            tabLinks[0].click();
        }
    });
    ";

  wp_add_inline_script('jquery', $script);
}
add_action('wp_enqueue_scripts', 'custom_tabs_scripts');


// Register the shortcode
add_shortcode('property_gallery_slideshow', 'property_gallery_slideshow_shortcode');

function enqueue_swiper_assets()
{
  // Enqueue Swiper CSS
  wp_enqueue_style('swiper-css', 'https://unpkg.com/swiper/swiper-bundle.min.css');

  // Enqueue Swiper JS
  wp_enqueue_script('swiper-js', 'https://unpkg.com/swiper/swiper-bundle.min.js', array(), null, true);

  // Initialize Swiper
  wp_add_inline_script('swiper-js', '
        document.addEventListener("DOMContentLoaded", function() {
            var swiper = new Swiper(".property-gallery-slideshow", {
                loop: true,
				slidesPerView: 3,
				spaceBetween: 20,
                navigation: {
                    nextEl: ".custom-swiper-button-next",
                    prevEl: ".custom-swiper-button-prev",
                },
				breakpoints: {
                    0: { slidesPerView: 1 },  // Mobile: 1 image per view
                    768: { slidesPerView: 2 }, // Tablet: 2 images per view
                    1024: { slidesPerView: 3 } // Desktop: 3 images per view
                },
            });
        });
    ');
}
add_action('wp_enqueue_scripts', 'enqueue_swiper_assets');

function acf_number_shortcode($atts)
{
  $atts = shortcode_atts(array(
    'field_name' => '',
    'post_id' => '',
    'decimals' => 0,
    'currency' => '', // Optional: Currency symbol
  ), $atts);

  $field_value = get_field($atts['field_name'], $atts['post_id']);

  if (is_numeric($field_value)) {
    $formatted_value = number_format($field_value, $atts['decimals']);
    if (!empty($atts['currency'])) {
      $formatted_value = '<h5 style="padding-top:0px;padding-bottom:0px;padding-left:0px;padding-right:0px;margin-top:0px;margin-bottom:0px;margin-left:0px;margin-right:0px;" >' . $formatted_value . " " . $atts['currency'] . "</h5>";
    }
    return $formatted_value;
  } else {
    return 'Invalid number field.';
  }
}
add_shortcode('acf_number', 'acf_number_shortcode');


function gallery_grid_shortcode()
{
  // Start output buffering
  ob_start();

  // Array of ACF gallery image fields
  $gallery_fields = [
    'images_gallery_image_1',
    'images_gallery_image_2',
    'images_gallery_image_3',
    'images_gallery_image_4',
    'images_gallery_image_5',
    'images_gallery_image_6',
    'images_gallery_image_7',
    'images_gallery_image_8',
  ];

  // Check if any gallery image exists
  $has_images = false;
  foreach ($gallery_fields as $field) {
    if (get_field($field)) {
      $has_images = true;
      break;
    }
  }

  // Display the grid if images exist
  if ($has_images) {
    echo '<div class="gallery-grid">';

    foreach ($gallery_fields as $field) {
      $image = get_field($field);
      if ($image) {
        echo '<div class="gallery-item">';
        // echo '<a href="' . esc_url($image['url']) . '" data-fancybox="gallery">';
        echo '<img src="' . esc_url($image['url']) . '" alt="' . esc_attr($image['alt']) . '" loading="lazy">';
        // echo '</a>';
        echo '</div>'; // Close .gallery-item
      }
    }

    echo '</div>'; // Close .gallery-grid
  }

  // Return the output
  return ob_get_clean();
}

// Register the shortcode
add_shortcode('property_gallery_grid', 'gallery_grid_shortcode');

function property_amenities_list_shortcode($atts)
{

  // Extract shortcode attributes
  $atts = shortcode_atts(array(
    'lang' => 'en',      // Language attribute (en or ja)
  ), $atts);

  // Start output buffering
  ob_start();

  // Get ACF field values
  $bedrooms = get_field('property_amenities_bedrooms');
  $toilets = get_field('property_amenities_toilets');
  $property_type = esc_attr($atts['lang']) == 'en' ? get_field('general_property_type') : get_field('general_property_type_ja');

  $landSizeProps = ['holidayHome', 'house'];

  if (in_array($property_type, $landSizeProps, true)) {
    $land_size = get_field('general_land_size');
  } else {
    $land_size = get_field('general_total_floor_size');
  }


  if ($property_type)


    // Check if any field has a value
    if ($bedrooms || $toilets || $land_size) {
      echo '<ul class="property-amenities-list">';

      if ($bedrooms) {
        echo '<li>' . esc_html($bedrooms);
        if (esc_attr($atts['lang']) == 'en') {
          echo ' Bedrooms</li>';
          if ($bedrooms > 1) {
            echo ' Bedrooms</li>';
          } else {
            echo ' Bedroom</li>';
          }
        } else {
          echo ' LDK</li>';
        }
      }

      if ($toilets && $bedrooms) {
        echo '<li class="property-amenities-border-left">' . esc_html($toilets);
        if (esc_attr($atts['lang']) == 'en') {
          if ($toilets > 1) {
            echo ' Baths</li>';
          } else {
            echo ' Bath</li>';
          }
        } else {
          echo ' トイレ</li>';
        }
      }

      if ($toilets && !$bedrooms) {
        echo '<li>' . esc_html($toilets);

        if (esc_attr($atts['lang']) == 'en') {
          echo ' Baths</li>';
        } else {
          echo ' トイレ</li>';
        }
      }

      if ($land_size && ($toilets || $bedrooms)) {
        echo '<li class="property-amenities-border-left">' . esc_html($land_size) . ' m²</li>';
      } else if ($land_size && (!$toilets || !$bedrooms)) {
        echo '<li>' . esc_html($land_size) . ' m²</li>';
      }

      echo '</ul>'; // Close .property-amenities-list
    }

  // Return the output
  return ob_get_clean();
}

// Register the shortcode
add_shortcode('property_amenities_list', 'property_amenities_list_shortcode');

/*
END Single Property functions.
*/

/*
START reading time function.
*/

function calculate_reading_time($post_id)
{
  // Get the post content
  $post_content = get_post_field('post_content', $post_id);

  // Strip HTML tags and shortcodes to get the plain text
  $text_content = strip_tags(strip_shortcodes($post_content));

  // Count the number of words
  $word_count = str_word_count($text_content);

  // Calculate the reading time (200 words per minute)
  $reading_time = ceil($word_count / 200);

  return $reading_time;
}

function save_reading_time_to_acf($post_id)
{
  // Check if this is not an autosave
  if (wp_is_post_autosave($post_id)) {
    return;
  }

  // Check if the user has permissions to save the post
  if (!current_user_can('edit_post', $post_id)) {
    return;
  }

  // Calculate the reading time
  $reading_time = calculate_reading_time($post_id);

  // Add "min" or "mins" based on the reading time
  $reading_time_text = $reading_time . ' ' . ($reading_time == 1 ? 'min' : 'mins');

  // Update the ACF field with the reading time and text
  update_field('reading_time', $reading_time_text, $post_id);
}
add_action('save_post', 'save_reading_time_to_acf');

/*
END reading time function.
*/

function properties_list_shortcode($atts)
{
  $is_inquiry = isset($atts['is_inquiry'])
    ? filter_var($atts['is_inquiry'], FILTER_VALIDATE_BOOLEAN)
    : true;  // <-- default true

  // define number of properties shown in the first rendering, default is 9
  $number_of_post = !empty($atts['number_of_post']) ? intval($atts['number_of_post']) : 9;

  // Extract shortcode attributes
  $atts = shortcode_atts(array(
    'post_type' => 'property', // Default post type
    'posts_per_page' => -1,          // Get all posts (we'll handle pagination)
    'orderby' => 'date',     // Order by date to get the latest posts first
    'order' => 'DESC',      // Display in descending order (newest first)
    'taxonomy' => '',         // Taxonomy name (e.g., 'property_type')
    'term' => '',         // Taxonomy term slug (e.g., 'condo')
    'lang' => 'en',      // Language attribute (en or ja)
  ), $atts);

  // Get the current post ID
  $current_post_id = get_the_ID();

  // Define the query arguments
  $args = array(
    'post_type' => $atts['post_type'], // Use dynamic post type
    'posts_per_page' => $atts['posts_per_page'], // Number of posts to display
    'orderby' => $atts['orderby'], // Randomize the results (optional)
  );

  // Add taxonomy filter if provided
  if (!empty($atts['taxonomy']) && !empty($atts['term'])) {
    $args['tax_query'] = array(
      array(
        'taxonomy' => $atts['taxonomy'], // Taxonomy name (e.g., 'property_type')
        'field' => 'slug',            // Use term slug
        'terms' => $atts['term'],     // Term slug (e.g., 'condo')
      ),
    );
  }

  // Run the query
  $query = new WP_Query($args);

  // Start the output
  $output = '<div class="similar-properties" 
              data-lang="' . esc_attr($atts['lang']) . '" 
              data-offset="' . esc_attr($number_of_post) . '">';

  // START ADD Sorting  functionality 25/10/03
  $sorted_posts = $query->posts;

  usort($sorted_posts, function ($a, $b) {
    // Assume 'general' is the ACF group field
    $a_general = get_field('general', $a->ID);
    $b_general = get_field('general', $b->ID);

    $a_pickedup = $a_general['is_pickedup'] ?? false;
    $b_pickedup = $b_general['is_pickedup'] ?? false;

    $a_available = $a_general['is_available'] ?? false;
    $b_available = $b_general['is_available'] ?? false;

    $a_pickedup = filter_var($a_pickedup, FILTER_VALIDATE_BOOLEAN);
    $b_pickedup = filter_var($b_pickedup, FILTER_VALIDATE_BOOLEAN);
    $a_available = filter_var($a_available, FILTER_VALIDATE_BOOLEAN);
    $b_available = filter_var($b_available, FILTER_VALIDATE_BOOLEAN);

    // If not available, override picked-up
    $a_priority = !$a_available ? 2 : ($a_pickedup ? 0 : 1);
    $b_priority = !$b_available ? 2 : ($b_pickedup ? 0 : 1);

    // First by priority
    if ($a_priority !== $b_priority) {
      return $a_priority <=> $b_priority;
    }

    // If same priority, latest date first
    return strtotime($b->post_date) <=> strtotime($a->post_date);
  });


  // Replace the query's post list
  $query->posts = $sorted_posts;

  // END ADD Sorting  functionality 25/10/03

  // Check if there are posts
  if ($query->have_posts()):
    $count = 0;
    while ($query->have_posts()):
      $query->the_post();
      $count++;
      // Only show first $number_of_post properties initially
      $style = $count > $number_of_post ? ' style="display:none;"' : '';

      // Get the ACF field values
      $general_layout = get_field('general_layout');
      $general_title = get_field('general_title');
      $general_price = get_field('general_price');
      $general_sub_title = get_field('general_sub_title');
      // Start the property card with a link to the property details page
      $output .= '<div class="property-card"' . $style . ' data-index="' . $count . '">';

      $output .= '<a href="' . get_the_permalink() . '" >';

      // START EDIT Added for Close event for property/categories 25/10/03 
      // Display the featured image
      // Get general ACF data
      $general = get_field('general', get_the_ID());
      $is_available = $general['is_available'] ?? null;
      $lang = $general['language'] ?? null; // en or ja

      if (has_post_thumbnail()) {
        // Build the class for property-image div
        $class = 'property-image';
        if (!$is_available) {
          $class .= ' not_available ' . esc_attr($lang);
        }
        $post_id = get_the_ID();
        $status_class = get_property_status_class($post_id);
        if ($status_class) {
          $class .= ' ' . $status_class;
        }

        // Append the div with proper class directly
        $output .= '<div class="' . $class . '"'
          . get_post_date_attribute(get_the_ID())
          . '>';
        $output .= get_the_post_thumbnail(get_the_ID(), 'full', array('class' => 'property-thumbnail'));
        $output .= '</div>';
      }

      // END EDIT Added for Close event for property/categories 25/10/03

      $output .= '<div class="property-wrap">';

      // Display the general layout (if applicable)
      if ($general_layout) {
        $output .= '<div class="property-layout">' . esc_html($general_layout) . '</div>';
      }

      // Display the general title
      if ($general_title) {
        $output .= '<h3 class="property-title">' . esc_html($general_title) . '</h3>';
      }

      // Display the general price
      $is_abroad_child = is_under_abroad($atts['taxonomy'], $atts['term']);

      if ($is_abroad_child) {
        // Price in USD
        $output .= '<div class="property-price">US$ ' . esc_html(format_number_with_commas($general_price, 0)) . '</div>';
      } else {
        // Price in Yen
        $output .= '<div class="property-price">¥ ' . esc_html(format_number_with_commas($general_price, 0)) . '</div>';
      }

      // Display the general sub title
      if ($general_sub_title) {
        $output .= '<div class="property-sub-title">' . esc_html($general_sub_title) . '</div>';
      }

      $output .= '</a>';

      // Add "Inquire Now" button
      if ($is_inquiry) {
        $output .= get_inquiry_button_html(get_the_ID(), esc_attr($atts['lang']));
      }
      // End the property wrap
      $output .= '</div>';

      // End the property card
      $output .= '</div>';
    endwhile;

    // Add View More button if there are more than $number_of_post properties
    if ($count > $number_of_post) {
      $output .= '<div class="view-more-container">';
      $output .= '<button class="view-more-button">View More</button>';
      $output .= '</div>';
    }

    wp_reset_postdata(); // Reset the post data
  else:
    $output .= '<div class="no-properties-container">';
    if (esc_attr($atts['lang']) == 'en') {
      $output .= '<p>No properties found.</p>';
    } else {
      $output .= '<p>物件は見つかりませんでした。</p>';
    }
    $output .= '</div>';
  endif;

  // End the output
  $output .= '</div>';

  $output .= '<script type="text/javascript">
    document.addEventListener("DOMContentLoaded", function() {
        // Debugging - confirm script is loading
        console.log("Properties list script loaded");

        // Use event delegation for dynamic elements
        document.body.addEventListener("click", function(e) {
            // Check if clicked element or its parent has the view-more-button class
            const button = e.target.closest(".view-more-button");
            if (!button) return;

            console.log("View More button detected");

            const container = button.closest(".similar-properties");
            if (!container) {
                console.error("Could not find .similar-properties container");
                return;
            }

            const offset = parseInt(container.dataset.offset) || 6;
            const nextOffset = offset + 6;
            const propertyCards = container.querySelectorAll(".property-card");

            console.log(`Showing items ${offset} to ${nextOffset} of ${propertyCards.length}`);

            // Show next 6 properties with animation
            let showedAny = false;
            for (let i = offset; i < nextOffset && i < propertyCards.length; i++) {
                propertyCards[i].style.display = "block";
                propertyCards[i].style.animation = "fadeIn 0.3s ease-out";
                showedAny = true;
            }

            if (!showedAny) {
                console.log("No more properties to show");
                button.style.display = "none";
                return;
            }

            // Update offset
            container.dataset.offset = nextOffset;

            // Hide button if all properties are shown
            if (nextOffset >= propertyCards.length) {
                button.style.opacity = "0.5";
                button.textContent = "All Properties Shown";
                setTimeout(() => {
                    button.style.display = "none";
                }, 1000);
            }
        });
    });
    </script>';

  // Add some basic CSS for the animation
  $output .= '<style>
    @keyframes fadeIn {
        from { opacity: 0; transform: translateY(10px); }
        to { opacity: 1; transform: translateY(0); }
    }
    .property-card[style*="display: none"] {
        display: none !important;
    }
    </style>';

  return $output;
}
add_shortcode('properties_list', 'properties_list_shortcode');

function acf_checkbox_shortcode($atts)
{
  $atts = shortcode_atts(
    array(
      'field' => 'property_amenities_features', // Default field name
      'post_id' => get_the_ID() // Default to current post
    ),
    $atts,
    'acf_checkbox'
  );

  $values = get_field($atts['field'], $atts['post_id']);

  // Check if field has values
  if (empty($values) || !is_array($values))
    return '';

  // Get the field object to access label names
  $field_object = get_field_object($atts['field']);
  if (!$field_object || empty($field_object['choices']))
    return '';

  $choices = $field_object['choices']; // All possible checkbox labels
  $total_items = count($values);

  // Determine column count
  $rows_per_column = 4;
  $column_count = ($total_items > 8) ? ceil($total_items / $rows_per_column) : (($total_items > 4) ? 2 : 1);
  $chunks = array_chunk($values, $rows_per_column); // Split values into rows

  $output = '<div class="acf-checkbox-container columns-' . $column_count . '">';

  foreach ($chunks as $chunk) {
    $output .= '<ul>';
    foreach ($chunk as $value) {
      $label = isset($choices[$value]) ? $choices[$value] : $value;
      $output .= '<li>' . esc_html($label) . '</li>';
    }
    $output .= '</ul>';
  }

  $output .= '</div>';

  return $output;
}
add_shortcode('acf_checkbox', 'acf_checkbox_shortcode');


function get_current_user_language()
{
  // Check for WPML
  if (function_exists('icl_object_id')) {
    return ICL_LANGUAGE_CODE;
  } elseif (function_exists('pll_current_language')) {
    return pll_current_language();
  } elseif (isset($_GET['lang'])) {
    return sanitize_text_field($_GET['lang']);
  } elseif (isset($_COOKIE['user_language'])) {
    return sanitize_text_field($_COOKIE['user_language']);
  }
  return 'en';
}

function get_multilingual_amenities_options()
{
  return array(
    'loft' => array(
      'en' => 'Loft',
      'jp' => 'ロフト'
    ),
    'balcony' => array(
      'en' => 'Balcony',
      'jp' => 'バルコニー'
    ),
    'roof_balcony' => array(
      'en' => 'Roof balcony',
      'jp' => 'ルーフバルコニー'
    ),
    'wood_deck' => array(
      'en' => 'Wood deck',
      'jp' => 'ウッドデッキ'
    ),
    'air-conditioning' => array(
      'en' => 'Air-conditioning',
      'jp' => 'エアコン'
    ),
    'floor_heating' => array(
      'en' => 'Floor heating',
      'jp' => '床暖房'
    ),
    'bathroom_dryer' => array(
      'en' => 'Bathroom dryer',
      'jp' => '浴室乾燥機'
    ),
    'systemized_kitchen' => array(
      'en' => 'Systemized kitchen',
      'jp' => 'システムキッチン'
    ),
    'dishwasher' => array(
      'en' => 'Dishwasher',
      'jp' => '食器洗い乾燥機'
    ),
    'all_electric' => array(
      'en' => 'All electric',
      'jp' => 'オール電化'
    ),
    'oven' => array(
      'en' => 'Oven',
      'jp' => 'オーブン'
    ),
    'walk-in_closet' => array(
      'en' => 'Walk-in closet',
      'jp' => 'ウォークインクローゼット'
    ),
    'shoe-in_closet' => array(
      'en' => 'Shoe-in closet',
      'jp' => 'シューズインクローゼット'
    ),
    'auto-lock' => array(
      'en' => 'Auto-lock',
      'jp' => 'オートロック'
    ),
    'security_camera' => array(
      'en' => 'Security camera',
      'jp' => '防犯カメラ'
    ),
    'elevator' => array(
      'en' => 'Elevator',
      'jp' => 'エレベーター'
    ),
    'delivery_box' => array(
      'en' => 'Delivery box',
      'jp' => '宅配BOX'
    ),
    'garden' => array(
      'en' => 'Garden',
      'jp' => '庭'
    ),
    'storage' => array(
      'en' => 'Storage',
      'jp' => '倉庫'
    ),
    'furnished' => array(
      'en' => 'Furnished',
      'jp' => '家具・家電付'
    ),
    'with_lighting_fixtures' => array(
      'en' => 'With lighting fixtures',
      'jp' => '照明器具付き'
    ),
    'fully_leased' => array(
      'en' => 'Fully leased',
      'jp' => '満室賃貸中'
    ),
    'renovated' => array(
      'en' => 'Renovated',
      'jp' => 'リフォーム済'
    ),
    'hotel_inn_business_license_obtained' => array(
      'en' => 'Hotel/Inn business license obtained',
      'jp' => 'ホテル・旅館営業許可'
    ),
    'private_accommodation_operation_available' => array(
      'en' => 'Private accommodation operation available',
      'jp' => '民泊可'
    ),
    'owner_change' => array(
      'en' => 'Owner change',
      'jp' => 'オーナーチェンジ'
    )
  );
}


function display_feature_amenities_shortcode($atts)
{
  $atts = shortcode_atts(
    array(
      'field' => 'property_amenities_features', // Default field name
      'post_id' => get_the_ID(), // Default to current post
    ),
    $atts,
    'acf_checkbox'
  );

  $values = get_field($atts['field'], $atts['post_id']);

  // Check if field has values
  if (empty($values) || !is_array($values))
    return '';

  // Get the field object to access label names
  $field_object = get_field_object($atts['field']);
  if (!$field_object || empty($field_object['choices']))
    return '';

  $current_lang = get_current_user_language();
  $multilingual_options = get_multilingual_amenities_options();

  $total_items = count($values);
  $column_class = ($total_items > 4) ? 'two-columns' : 'one-column';

  // Start output
  $output = '<div class="acf-checkbox-container ' . $column_class . '"><ul>';

  foreach ($values as $index => $value) {
    // Display the label instead of value
    $label = isset($multilingual_options[$value][$current_lang]) ?
      $multilingual_options[$value][$current_lang] :
      $value;
    $output .= '<li>' . esc_html($label) . '</li>';

    // Break into a new row every 4 items
    if (($index + 1) % 4 == 0 && ($index + 1) < $total_items) {
      $output .= '</ul><ul>';
    }
  }

  $output .= '</ul></div>';

  return $output;
}
add_shortcode('display_feature_amenities', 'display_feature_amenities_shortcode');



/*
START Multilingual post date format function.
*/
function multilingual_post_date_shortcode()
{
  // Check if we're in the loop and on a singular post/page
  if (!is_singular() || !in_the_loop()) {
    global $post;
  }

  // Get current Polylang language
  $current_lang = function_exists('pll_current_language') ? pll_current_language() : 'en';
  $prefix = '';
  // Set date format and prefix based on language
  switch ($current_lang) {
    case 'ja':
    case 'jp': // Japanese
      $date_format = 'Y年m月j日';
      break;
    default: // English or others
      $date_format = 'F j, Y';

      $prefix = is_singular() ? 'Posted on ' : '';
      break;
  }

  // Return date with prefix
  return $prefix . get_the_date($date_format);
}
add_shortcode('multilang_date', 'multilingual_post_date_shortcode');


/*
END Multilingual post date format function.
*/

// Remove reading time tags from Twitter meta tags (Slack preview)
add_filter('wpseo_twitter_card_type', function ($type) {
  // Just in case Yoast is overriding Twitter card format
  return 'summary_large_image';
});

add_action('wpseo_head', function () {
  ob_start(function ($output) {
    // Remove any tags you don't want, like reading time
    $output = preg_replace('/<meta name="twitter:label1".*?>/', '', $output);
    $output = preg_replace('/<meta name="twitter:data1".*?>/', '', $output);
    return $output;
  });
});


// Register shortcode to display ACF oEmbed videos in a 3-column layout
function acf_videos_row_shortcode()
{
  $video1 = get_field('videos_embed_video_1');
  $video2 = get_field('videos_embed_video_2');
  $video3 = get_field('videos_embed_video_3');

  // Count how many videos are actually provided
  $videos = array_filter([$video1, $video2, $video3]);
  $video_count = count($videos);

  if ($video_count > 0) {
    ob_start();

    echo '<div class="acf-video-scroll-wrapper">';
    echo '<div class="acf-video-row">';

    foreach ($videos as $video) {
      // If only one video is provided, make it full width
      $column_class = ($video_count === 1) ? 'acf-video-full-width' : 'acf-video-column';
      echo '<div class="' . esc_attr($column_class) . '"><div class="acf-video-embed">' . $video . '</div></div>';
    }

    echo '</div>';
    echo '</div>';

    return ob_get_clean();
  }

  return '';
}
add_shortcode('acf_videos_row', 'acf_videos_row_shortcode');

function enqueue_fancybox_assets()
{
  wp_enqueue_style('fancybox-css', 'https://cdn.jsdelivr.net/npm/@fancyapps/ui/dist/fancybox.css');
  wp_enqueue_script('fancybox-js', 'https://cdn.jsdelivr.net/npm/@fancyapps/ui/dist/fancybox.umd.js', array(), null, true);
}
add_action('wp_enqueue_scripts', 'enqueue_fancybox_assets');

function floor_plan_grid_shortcode($atts)
{
  $atts = shortcode_atts(array(
    'lang' => 'en',
  ), $atts);

  ob_start();
  $language = get_field('general_language');
  $label_suffix = ($language === 'en') ? 'en' : 'ja';

  $label_1 = get_field("images_floor_plan_label_1_{$label_suffix}");
  $label_2 = get_field("images_floor_plan_label_2_{$label_suffix}");
  $label_3 = get_field("images_floor_plan_label_3_{$label_suffix}");
  $label_4 = get_field("images_floor_plan_label_4_{$label_suffix}");

  // Floor plan fields with labels
  $floor_plans = [
    'images_floor_plan_1' => $label_1,
    'images_floor_plan_2' => $label_2,
    'images_floor_plan_3' => $label_3,
    'images_floor_plan_4' => $label_4,
  ];

  $images = [];
  foreach ($floor_plans as $field => $label) {
    $image = get_field($field);
    if ($image) {
      $images[] = ['url' => $image['url'], 'alt' => $image['alt'], 'label' => $label];
    }
  }

  if (count($images) > 0) {
    // Determine grid style
    // $grid_class = count($images) > 1 ? 'floor-plan-grid-2x2' : 'floor-plan-grid-full-width';

    $grid_class = 'floor-plan-grid-full-width';

    echo '<div class="floor-plan-grid-wrapper">';
    echo '<h3 class="floor-plan-heading">' . ($atts['lang'] === 'en' ? 'FLOORPLAN' : 'フロアプラン') . '</h3>';
    echo '<div class="floor-plan-grid ' . esc_attr($grid_class) . '">';

    foreach ($images as $image) {
      echo '<div class="floor-plan-item">';
      echo '<a href="' . esc_url($image['url']) . '" class="floor-plan-popup" data-fancybox="floorplan" title="' . esc_attr($image['label']) . '">';
      echo '<img src="' . esc_url($image['url']) . '" alt="' . esc_attr($image['alt']) . '">';
      echo '</a>';
      echo '<p class="floor-label">' . esc_html($image['label']) . '</p>';
      echo '</div>';
    }

    echo '</div>'; // .floor-plan-grid
    echo '</div>'; // .floor-plan-grid-wrapper
  }

  return ob_get_clean();
}
add_shortcode('floor_plan_grid', 'floor_plan_grid_shortcode');


// Add and reorder columns
function custom_property_columns($columns)
{
  // Create a new array to reorder columns
  $new_columns = [];

  foreach ($columns as $key => $title) {
    if ($key === 'date') {
      // Insert our custom column before the date column
      $new_columns['general_property_type'] = 'Property Type';
    }

    // Preserve the existing column
    $new_columns[$key] = $title;
  }

  return $new_columns;
}
add_filter('manage_property_posts_columns', 'custom_property_columns');

// Output content for custom column
function custom_property_column_content($column, $post_id)
{
  if ($column === 'general_property_type') {
    $lang = get_post_meta($post_id, 'general_language', true);
    if ($lang === 'en') {
      $label = get_property_type_label(get_post_meta($post_id, 'general_property_type', true), 'en');
      // echo esc_html($label).' - '. esc_html(get_post_meta($post_id, 'general_property_type', true));
      echo esc_html($label);
    } else {
      $label = get_property_type_label(get_post_meta($post_id, 'general_property_type_ja', true), 'ja');
      // echo esc_html($label).' - '. esc_html(get_post_meta($post_id, 'general_property_type_ja', true));
      echo esc_html($label);
    }
  }
}
add_action('manage_property_posts_custom_column', 'custom_property_column_content', 10, 2);

function display_author_profile_shortcode()
{
  global $post;

  if (!$post)
    return '';

  $author_id = $post->post_author;
  $author_name = get_the_author_meta('display_name', $author_id);
  $author_bio = get_the_author_meta('description', $author_id);

  if (empty($author_bio))
    return '';

  $author_photo = get_field('author_photo', 'user_' . $author_id);

  // ✅ Use media library image URL as default fallback
  $default_image_url = 'https://mrland.co.jp/wp-content/uploads/2025/02/mrland-black-logo.png';

  $photo_url = $author_photo && isset($author_photo['url']) ? $author_photo['url'] : $default_image_url;

  $current_lang = function_exists('pll_current_language') ? pll_current_language() : 'en';

  $heading = match ($current_lang) {
    'ja', 'jp' => '<h2 class="author-header"><span class="mrl-orange">ラ</span>イターのご紹介</h2>',
    default => '<h2 class="author-header"><span class="mrl-orange">A</span>bout the Author</h2>',
  };

  ob_start();
  ?>
  <div class="author-wrapper">
    <?php echo $heading; ?>
    <div class="author-profile">
      <div class="author-photo">
        <img src="<?php echo esc_url($photo_url); ?>" alt="<?php echo esc_attr($author_name); ?>">
      </div>
      <div class="author-info">
        <h3 class="author-name"><?php echo esc_html($author_name); ?></h3>
        <p class="author-bio"><?php echo nl2br(esc_html($author_bio)); ?></p>
      </div>
    </div>
  </div>

  <?php
  return ob_get_clean();
}
add_shortcode('author_profile', 'display_author_profile_shortcode');

// START ADD Automated prepending or deleting string from ACF attribute 25/10/03

add_action('acf/save_post', function ($post_id) {
  // This will be automatically executed when property post is updated.

  if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE)
    return;
  if (get_post_type($post_id) !== 'property')
    return; // return if the post is not property 
  if (!is_admin())
    return;

  // Bail early if ACF is not saving this post
  if (!isset($_POST['acf']))
    return;

  $is_available = get_field('general_is_available', $post_id);
  $language = get_field('general_language', $post_id); // 'en' or 'ja'
  $title = get_field('general_title', $post_id);   // ACF title field

  $label = ($language === 'ja') ? '【成約済み】' : '【CLOSED】';

  // Remove existing labels
  $clean_title = preg_replace('/^(【CLOSED】|【成約済み】)/u', '', $title);

  if (!$is_available) {
    $new_title = $label . $clean_title;
  } else {
    $new_title = $clean_title;
  }

  if ($new_title !== $title) {
    update_field('general_title', $new_title, $post_id); // update ACF title
  }

  $post = get_post($post_id);

  if ($new_title !== $post->post_title) {
    wp_update_post(['ID' => $post_id, 'post_title' => $new_title,]); // update post title
  }
}, 20);

// END ADD Automated prepending or deleting string from ACF attribute 25/10/03

// START ADD inquiry button attached to each property in property list
function get_inquiry_button_html($post_id = null, $lang = 'en')
{
  if (!$post_id) {
    $post_id = get_the_ID();
  }
  $inquiry_link = get_permalink($post_id) . '#contact-us';
  $button_text = ($lang === 'en') ? 'Inquire Now' : '問い合わせる';
  $button_html = '<a href="' . esc_url($inquiry_link) . '"><div class="inquire-button">' . esc_html($button_text) . '</div></a>';
  return $button_html;
}
// END ADD inquiry button attached to each property in property list

/**
 * Returns a HTML data attribute containing the post's publish date in ISO 8601 format.
 *
 * This is useful for adding a post date to an HTML element so that JavaScript
 * can easily access and manipulate it, e.g., comparing to the current date,
 * calculating the age of the post, or displaying "new" labels.
 *
 * Example output:
 * <div class="property-card" data-post-date="2025-01-27T15:12:00+09:00"></div>
 *
 * @param int $post_id The ID of the WordPress post.
 * @return string A string like ' data-post-date="2025-01-27T15:12:00+09:00"' 
 *                or an empty string if $post_id is invalid.
 */
function get_post_date_attribute($post_id)
{
  if (!$post_id)
    return '';

  // Get post date in ISO 8601 format (preferred for JS)
  $post_date = get_the_date('c', $post_id);

  // Return as a safe HTML attribute
  return ' data-post-date="' . esc_attr($post_date) . '"';
}

/**
 * Get "picked-up" property posts based on property type and language.
 *
 * This function returns a WP_Query object containing posts where:
 *  - post_type matches the provided $post_type
 *  - general_is_pickedup (boolean ACF field) = 1
 *  - property type matches (optional)
 * 
 * It also prints useful console logs in the browser for debugging.
 *
 * @param string $property_type     Comma-separated property types (e.g., "house,condo")
 * @param string $post_type         Custom post type (default: "property")
 * @param string $lang              Language ("en" or "ja") to match the correct ACF field
 * @param int    $posts_per_page    How many picked-up posts to fetch
 *
 * @return WP_Query                 WP_Query object containing picked-up posts
 */

function get_pickedup_properties($property_type = '', $post_type = 'property', $lang = 'en', $posts_per_page = 3)
{

  // Build meta query
  $meta_query = array();

  if (!empty($property_type)) {
    $property_types = array_map('trim', explode(',', $property_type));

    $meta_query[] = array(
      'key' => $lang === 'en' ? 'general_property_type' : 'general_property_type_ja',
      'value' => count($property_types) > 1 ? $property_types : $property_types[0],
      'compare' => count($property_types) > 1 ? 'IN' : '='
    );
  }

  // Boolean field
  $meta_query[] = array(
    'key' => 'general_is_pickedup',
    'value' => 1,
    'compare' => '='
  );

  $meta_query[] = array(
    'key' => 'general_is_available',
    'value' => 1,
    'compare' => '='
  );

  // Build WP_Query args
  $args = array(
    'post_type' => $post_type,
    'posts_per_page' => $posts_per_page,
    'orderby' => 'date',
    'order' => 'DESC',
    'meta_query' => $meta_query,
  );

  $query = new WP_Query($args);

  // Prepare posts for logging
  $posts_for_log = array();
  foreach ($query->posts as $p) {
    $posts_for_log[] = [
      'ID' => $p->ID,
      'title' => get_the_title($p->ID)
    ];
  }

  return $query;
}

/**
 * Replaces the posts in a WP_Query object with a custom array of posts.
 *
 * This function updates the WP_Query object so that it behaves as if it originally
 * queried only the posts provided in $new_posts. It also updates internal counters
 * and resets the loop pointer to ensure the query works properly in loops.
 *
 * @param WP_Query $query     The original WP_Query object to modify.
 * @param array    $new_posts An array of WP_Post objects to replace the original query posts.
 *
 * @return WP_Query The modified WP_Query object with posts replaced by $new_posts.
 *
 * @example
 * $merged_posts = array_merge($pickedup_posts, $latest_posts);
 * $query = update_query_posts($query, $merged_posts);
 */
function update_query_posts($query, $new_posts)
{
  // Replace posts
  $query->posts = $new_posts;

  // Update counts
  $query->post_count = count($new_posts);
  $query->found_posts = count($new_posts);
  $query->max_num_pages = 1;

  // Reset internal loop pointer
  $query->rewind_posts();

  return $query;
}



function get_property_status_class($post_id)
{
  $general = get_field('general', get_the_ID());
  $is_pickedup = $general['is_pickedup'] ?? null;
  $is_available = $general['is_available'] ?? null;


  // If both conditions are true → return class
  if ($is_pickedup && $is_available) {
    return 'picked-up-property';
  }

  return '';
}

/**
 * Check if a term's top-level ancestor is "abroad"
 *
 * @param string $taxonomy   Taxonomy name (e.g., 'location-type')
 * @param string $term_slug  Term slug to check
 * @return bool              True if the top-level ancestor is "abroad", false otherwise
 */
function is_under_abroad($taxonomy, $term_slug)
{

  // Get the term object
  $term = get_term_by('slug', $term_slug, $taxonomy);
  if (!$term)
    return false;

  // Traverse up to the top ancestor
  $ancestor = $term;
  while ($ancestor->parent != 0) {
    $ancestor = get_term($ancestor->parent, $taxonomy);
    if (!$ancestor)
      return false; // safety check
  }

  // Check if the top ancestor slug is "abroad"
  return ($ancestor->slug === 'abroad');
}

// [custom_property_search]
function custom_property_search_shortcode($atts)
{
  $atts = shortcode_atts([
    'lg' => 'en'
  ], $atts);

  $lg = $atts['lg']; // language from shortcode attribute

  ob_start();
  ?>

  <div id="property-search-box" data-lang="<?php echo esc_attr($lg); ?>">
    <input type="text" id="ps-input" placeholder="Search property...">
    <button id="ps-btn">
      <svg class="ct-icon ct-search-button-content" aria-hidden="true" width="15" height="15" viewBox="0 0 15 15">
        <path
          d="M14.8,13.7L12,11c0.9-1.2,1.5-2.6,1.5-4.2c0-3.7-3-6.8-6.8-6.8S0,3,0,6.8s3,6.8,6.8,6.8c1.6,0,3.1-0.6,4.2-1.5l2.8,2.8c0.1,0.1,0.3,0.2,0.5,0.2s0.4-0.1,0.5-0.2C15.1,14.5,15.1,14,14.8,13.7z M1.5,6.8c0-2.9,2.4-5.2,5.2-5.2S12,3.9,12,6.8S9.6,12,6.8,12S1.5,9.6,1.5,6.8z">
        </path>
      </svg></button>
  </div>

  <div id="ps-results"></div>

  <script>
    let debounceTimer;
    const btn = document.getElementById("ps-btn");
    btn.addEventListener("keydown", (e) => {
      if (e.key === "Enter") {
        e.preventDefault();   // stops form submit / page reload
        const keyword = input.value.trim();

        if (!keyword) {
          resultsBox.innerHTML = "<p>Please type something.</p>";
          return;
        }

        doFullSearch(keyword);
      }
    });

    function debounceSearch() {
      clearTimeout(debounceTimer);
      debounceTimer = setTimeout(() => {
        startSearch();
      }, 500); // wait 0.5 second
    }

    document.getElementById('ps-input').addEventListener('input', debounceSearch);

    document.getElementById('ps-btn').addEventListener('click', function () {
      startSearch();
    });

    function getQueryParam(name) {
      const url = new URL(window.location.href);
      return url.searchParams.get(name);
    }


    async function startSearch() {
      const keyword = document.getElementById('ps-input').value.trim();
      const resultsBox = document.getElementById('ps-results');

      if (!keyword) {
        resultsBox.innerHTML = "<p>Please type something.</p>";
        return;
      }

      let currentPage = 1;
      const perPage = 50;

      async function loadResults(page = 1) {
        resultsBox.innerHTML = "<p>Searching...</p>";

        const url =
          `/wp-json/wp/v2/property?search=${encodeURIComponent(keyword)}&per_page=${perPage}&page=${page}`;

        btn.classList.add("ps-loading"); // start spinner

        try {
          const res = await fetch(url);
          const data = await res.json();

          const totalPages = parseInt(res.headers.get("X-WP-TotalPages"), 10);

          if (!data.length) {
            resultsBox.innerHTML = "<p>No results found.</p>";
            return;
          }

          let html = `<div class="grid-container">`;

          function formatPrice(price) {
            const num = parseInt(price, 10);
            return num.toLocaleString("en-US");
          }
          const searchBox = document.getElementById("property-search-box");
          const lang = searchBox.dataset.lang;
          console.log('DT', data)
          const filtered = data.filter(item =>
            item.acf?.general?.language === lang &&
            item.acf?.general?.is_available === true
          );
          if (!filtered.length) {
            resultsBox.innerHTML = "<p>No results found.</p>";
            return;
          }



          filtered.forEach(item => {
            console.log('ITEM', item)
            const acf = item.acf || {};

            const thumb = item.uagb_featured_image_src
              ? item.uagb_featured_image_src["2048x2048"][0]
              : "https://via.placeholder.com/300x200?text=No+Image";

            const price = acf.general?.price
              ? formatPrice(acf.general.price)
              : "—";

            const layout = acf.general?.layout;
            const subTitle = acf.general?.sub_title;

            const layoutHTML = layout
              ? `<div class='layout-tag-container'><span class="layout-tag">${layout}</span></div>`
              : "<div class='empty-layout-tag-container'></div>";

            html += `
                    <div class="ps-item">
                      <a href="${item.link}" class="ps-link">
                        <div class="ps-thumb">
                            <img src="${thumb}" alt="${item.title.rendered}">
                        </div>
                        ${layoutHTML}
                        <h3 class='ps-title'>${item.title.rendered}</h3>
                        <p class='ps-price'>¥ ${price}</p>
                        <h6 class='ps-sub-title'>${subTitle}</h6>
                      </a>
                      <div class='button-container'><button onclick="window.location.href='${item.link}#contact-us'">Inquire Now</button></div>
                    </div>
                `;
          });

          html += `</div>`;
          //             html += renderPagination(totalPages);

          resultsBox.innerHTML = html;

        } catch (err) {
          resultsBox.innerHTML = "<p>Error loading results.</p>";
        } finally {
          btn.classList.remove("ps-loading"); // stop spinner
        }
      }

      function renderPagination(totalPages) {
        if (totalPages <= 1) return "";

        let html = `<div class="ps-pagination">`;

        if (currentPage > 1) {
          html += `<span class="ps-prev" data-page="${currentPage - 1}">← Prev</span>`;
        }

        for (let i = 1; i <= totalPages; i++) {
          html += `<span class="ps-page ${i === currentPage ? 'active' : ''}" data-page="${i}">${i}</span>`;
        }

        if (currentPage < totalPages) {
          html += `<span class="ps-next" data-page="${currentPage + 1}">Next →</span>`;
        }

        html += `</div>`;
        return html;
      }

      //     document.getElementById("ps-results").addEventListener("click", (e) => {
      //         const page = e.target.dataset.page;
      //         if (page) {
      //             currentPage = parseInt(page, 10);
      //             loadResults(currentPage);
      // 			document.getElementById("ps-input").scrollIntoView({
      // 				behavior: "smooth",
      // 				block: "center"
      // 			});
      //         }
      //     });

      loadResults(1);
    }
    // Auto-run search if ?q= is in URL
    document.addEventListener("DOMContentLoaded", () => {
      const initial = getQueryParam("q");
      if (initial) {
        const input = document.getElementById("ps-input");
        input.value = initial;
        startSearch(); // auto-search
      }
    });

  </script>


  <?php
  return ob_get_clean();
}
add_shortcode('custom_property_search', 'custom_property_search_shortcode');

// [property_search_trigger]
function property_search_trigger_shortcode()
{
  ob_start(); ?>

  <div id="property-search-wrapper">

    <div id="property-search-box">
      <input type="text" id="ps-input" placeholder="Search property...">
      <button id="ps-btn">
        <svg class="ct-icon ct-search-button-content" aria-hidden="true" width="15" height="15" viewBox="0 0 15 15">
          <path d="M14.8,13.7L12,11c0.9-1.2,1.5-2.6,1.5-4.2c0-3.7-3-6.8-6.8-6.8S0,3,0,6.8s3,6.8,6.8,6.8
                    c1.6,0,3.1-0.6,4.2-1.5l2.8,2.8c0.1,0.1,0.3,0.2,0.5,0.2s0.4-0.1,0.5-0.2C15.1,14.5,15.1,14,14.8,13.7z M1.5,6.8
                    c0-2.9,2.4-5.2,5.2-5.2S12,3.9,12,6.8S9.6,12,6.8,12S1.5,9.6,1.5,6.8z"></path>
        </svg>
      </button>
    </div>

    <!-- Live result dropdown -->
    <div id="ps-live-results"></div>

    <!-- Full result box -->
    <div id="ps-results"></div>

  </div>

  <script>
    const wrapper = document.getElementById("property-search-wrapper");
    const input = document.getElementById("ps-input");
    const btn = document.getElementById("ps-btn");
    const resultsBox = document.getElementById("ps-results");
    const liveBox = document.getElementById("ps-live-results");


    input.addEventListener("keydown", (e) => {
      if (e.key === "Enter") {
        e.preventDefault();   // stops form submit / page reload
        const keyword = input.value.trim();

        if (!keyword) {
          resultsBox.innerHTML = "<p>Please type something.</p>";
          return;
        }

        goToPropertySearch(keyword);
      }
    });
    // result page //
    function goToPropertySearch(inputValue) {
      const domain = "https://test3.mrland.co.jp/result/"; // ← change to your result page
      const keyword = encodeURIComponent(inputValue.trim());

      if (!keyword) return;

      const url = `${domain}?q=${keyword}`;
      window.location.href = url;   // redirect user
    }



    /* ---- Debounce ---- */
    let debounceTimer;
    function debounce(func, delay) {
      clearTimeout(debounceTimer);
      debounceTimer = setTimeout(func, delay);
    }

    /* ---- Format price ---- */
    function formatPrice(price) {
      const num = parseInt(price, 10);
      return num.toLocaleString("en-US");
    }

    /* ---- Live Search ---- */
    input.addEventListener("input", () => {
      const keyword = input.value.trim();
      if (input.value.trim() !== keyword || keyword === "") {
        liveBox.innerHTML = "";
        return;
      }
      debounce(() => doLiveSearch(keyword), 300);
    });

    async function doLiveSearch(keyword) {
      console.log('Live search started')
      if (document.activeElement !== input) return;

      btn.classList.add("ps-loading"); // start spinner

      const url = `/wp-json/wp/v2/property?search=${encodeURIComponent(keyword)}&per_page=5&page=1`;

      try {
        const res = await fetch(url);
        const data = await res.json();
        const totalPages = parseInt(res.headers.get("X-WP-TotalPages"), 10);

        if (input.value.trim() !== keyword) return;

        if (!data.length) {
          liveBox.innerHTML = "";
          return;
        }

        let html = `<div class="live-list">`;

        data.forEach(item => {
          const thumb = item?.uagb_featured_image_src?.thumbnail?.[0]
            ?? "https://via.placeholder.com/80x60?text=No+Image";

          html += `
                    <div class="live-item">
                        <a href="${item.link}" class="ps-link">
                            <img src="${thumb}" class="live-thumb">
                            <p class="live-title">${item.title.rendered}</p>
                        </a>
                    </div>
                `;
        });

        html += `</div>`;

        if (totalPages > 1) {
          html += `
                    <button class="live-view-more" onclick="goToPropertySearch('${keyword}')">
                        Show more
                    </button>
                `;
        }

        liveBox.innerHTML = html;

      } finally {
        btn.classList.remove("ps-loading"); // stop spinner
      }
    }


    /* ---- button click ---- */
    btn.addEventListener("click", () => {
      const keyword = input.value.trim();
      if (!keyword) {
        resultsBox.innerHTML = "<p>Please type something.</p>";
        return;
      }
      goToPropertySearch(keyword);
    });


  </script>

  <?php
  return ob_get_clean();
}
add_shortcode('property_search_trigger', 'property_search_trigger_shortcode');

