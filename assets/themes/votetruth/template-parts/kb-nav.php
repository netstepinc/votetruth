<?php if ( ! defined( 'ABSPATH' ) ) {exit;}

//Get queried object and determin if Term or Post and identify Post Term
$qo = get_queried_object();
$term_id = 0;
if($qo instanceof WP_Term){
	$term = $qo;
	$term_id = $term->term_id;
}elseif($qo instanceof WP_Post){
	$post = $qo;
	$term = get_the_terms($post->ID, 'kb_cat');
	if(!empty($term) && is_array($term)){
		$term_id = $term[0]->term_id;
	}
}

$categories = get_terms(array(
	'taxonomy' => 'kb_cat',
	'hide_empty' => false,
));

$menu_items = [];
foreach ($categories as $category) {
	$active = $term_id == $category->term_id ? true : false;
	$children = [];
	if($active){
		$kids = get_posts(array(
			'post_type' => 'kb',
			'tax_query' => array(
				array(
					'taxonomy' => 'kb_cat',
					'field' => 'term_id',
					'terms' => $category->term_id,
				),
			),
			'posts_per_page' => -1,
		));

		foreach ($kids as $kid) {
			$children[] = [
				'url' => get_permalink($kid->ID),
				'slug' => $kid->post_name,
				'label' => $kid->post_title,
			];
		}
	}

	$menu_items[] = [
		'id' => $category->term_id,
		'url' => get_term_link($category),
		'slug' => $category->slug,
		'icon' => 'bi-folder',
		'label' => $category->name,
		'active' => $active,
		'count' => $category->count,
		'children' => $children,
	];
}
?>
<nav class="fi-kb-nav d-none d-md-block">
	<ul class="list-group rounded-4 shadow">
		<li class="list-group-item fw-bold bg-primary text-white">
			<a href="<?php echo home_url('/help/all/'); ?>" class="text-decoration-none">
				<i class="bi bi-house-fill me-2"></i> Knowledge Base
			</a>
		</li>
		<?php 
// ($item['active'] == true ? 'bg-success' : '')
// ($item['active'] == true ? 'fw-bold text-white' : '')
		foreach ($menu_items as $item) {
			echo '<li class="list-group-item"'.($item['active'] == true ? ' style="border-left: 5px solid #198754; border-bottom:1px solid #198754; border-top:1px solid #198754;"' : '').'>';
			echo '<a href="' . esc_url($item['url']) . '" class="text-decoration-none'.($item['active'] == true ? ' fw-bold' : '').'">';
			echo '<span class="d-flex justify-content-between align-items-center w-100"><span><i class="bi ' . esc_attr($item['icon']) . ' me-2"></i> ' . esc_html($item['label']) . '</span><span class="badge bg-secondary ms-auto">' . esc_html($item['count']) . '</span></span>';
			echo '</a>';
			if(!empty($item['children'])){
				echo '<ul class="list-group list-group-flush">';
				foreach ($item['children'] as $child) {
					echo '<li class="list-group-item px-0">';
					echo '<a href="' . esc_url($child['url']) . '" class="text-decoration-none">';
					echo $child['label'];
					echo '</a>';
					echo '</li>';
				}
				echo '</ul>';
			}
			echo '</li>';
		}
		?>
	</ul>
</nav>

<!-- Mobile Navigation -->
<nav class="fi-kb-nav-mobile d-md-none mb-3">
	<div class="dropdown">
		<button class="btn btn-outline-primary w-100 dropdown-toggle fw-bold p-2 fs-6" type="button" id="accountNavDropdown" data-bs-toggle="dropdown" aria-expanded="false">
			Knowledge Base Menu
		</button>
		<ul class="dropdown-menu w-100" aria-labelledby="accountNavDropdown">
			<?php 
			foreach ($menu_items as $item) {
				echo '<li class="dropdown-item' . ($item['active'] == true ? ' active bg-success' : '') . '">';
				echo '<a href="' . esc_url($item['url']) . '" class="text-decoration-none' . ($item['active'] == true ? ' active fw-bold text-white' : '') . '">';
				echo '<span class="d-flex justify-content-between align-items-center w-100"><span><i class="bi ' . esc_attr($item['icon']) . ' me-2"></i> ' . esc_html($item['label']) . '</span><span class="badge bg-secondary ms-auto">' . esc_html($item['count']) . '</span></span>';
				echo '</a>';
				if(!empty($item['children'])){
					echo '<ul class="list-group list-group-flush">';
					foreach ($item['children'] as $child) {
						echo '<li class="list-group-item">';
						echo '<a href="' . esc_url($child['url']) . '" class="text-decoration-none">';
						echo $child['label'];
						echo '</a>';
						echo '</li>';
					}
					echo '</ul>';
				}
				echo '</li>';
				echo '</li>';
			}
			?>
		</ul>
	</div>
</nav>


<?php
//if(get_current_user_id() == 1){echo '<textarea style="width: 100%; height: 400px;">';print_r($menu_items);echo '</textarea>';}


/* ALL CATS

Array
(
    [0] => WP_Term Object
        (
            [term_id] => 8
            [name] => Account
            [slug] => account
            [term_group] => 0
            [term_taxonomy_id] => 9015
            [taxonomy] => kb_cat
            [description] => 
            [parent] => 0
            [count] => 0
            [filter] => raw
        )

    [1] => WP_Term Object
        (
            [term_id] => 7
            [name] => Government
            [slug] => government
            [term_group] => 0
            [term_taxonomy_id] => 9014
            [taxonomy] => kb_cat
            [description] => 
            [parent] => 0
            [count] => 0
            [filter] => raw
        )

    [2] => WP_Term Object
        (
            [term_id] => 4
            [name] => Legislator
            [slug] => legislator
            [term_group] => 0
            [term_taxonomy_id] => 9011
            [taxonomy] => kb_cat
            [description] => 
            [parent] => 0
            [count] => 1
            [filter] => raw
        )

    [3] => WP_Term Object
        (
            [term_id] => 3
            [name] => Legislators
            [slug] => legislators
            [term_group] => 0
            [term_taxonomy_id] => 9010
            [taxonomy] => kb_cat
            [description] => 
            [parent] => 0
            [count] => 0
            [filter] => raw
        )

    [4] => WP_Term Object
        (
            [term_id] => 9
            [name] => Report
            [slug] => report
            [term_group] => 0
            [term_taxonomy_id] => 9016
            [taxonomy] => kb_cat
            [description] => 
            [parent] => 0
            [count] => 0
            [filter] => raw
        )

    [5] => WP_Term Object
        (
            [term_id] => 10
            [name] => Reports
            [slug] => reports
            [term_group] => 0
            [term_taxonomy_id] => 9017
            [taxonomy] => kb_cat
            [description] => 
            [parent] => 0
            [count] => 0
            [filter] => raw
        )

    [6] => WP_Term Object
        (
            [term_id] => 5
            [name] => Vote
            [slug] => vote
            [term_group] => 0
            [term_taxonomy_id] => 9012
            [taxonomy] => kb_cat
            [description] => 
            [parent] => 0
            [count] => 0
            [filter] => raw
        )

    [7] => WP_Term Object
        (
            [term_id] => 6
            [name] => Votes
            [slug] => votes
            [term_group] => 0
            [term_taxonomy_id] => 9013
            [taxonomy] => kb_cat
            [description] => 
            [parent] => 0
            [count] => 0
            [filter] => raw
        )

)


LEGISLATOR: WP_Term Object FOR CATEGORY
(
    [term_id] => 4
    [name] => Legislator
    [slug] => legislator
    [term_group] => 0
    [term_taxonomy_id] => 9011
    [taxonomy] => kb_cat
    [description] => 
    [parent] => 0
    [count] => 1
    [filter] => raw
)


TERM OBJECT OF KB POST
Array
(
    [0] => WP_Term Object
        (
            [term_id] => 4
            [name] => Legislator
            [slug] => legislator
            [term_group] => 0
            [term_taxonomy_id] => 9011
            [taxonomy] => kb_cat
            [description] => 
            [parent] => 0
            [count] => 1
            [filter] => raw
        )

)



*/