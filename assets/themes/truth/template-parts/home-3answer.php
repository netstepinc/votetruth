<?php if ( ! defined( 'ABSPATH' ) ) { exit; }
/*
EMPATHY + ANSWER
We get it. And we built something about it.
A campaign can make any politician sound good.
A voting record shows the pattern.
[SCORING GRID]
The Freedom Index scores every legislator against one standard: the Constitution. Not speeches. Not slogans. Not party labels. Recorded votes.
*/
define('FI_GRADES',[
    'A' => ['min' => 90, 'max' => 100, 'label' => 'Constitutional Champion'],
    'B' => ['min' => 80, 'max' => 89, 'label' => 'Generally Reliable'],
    'C' => ['min' => 70, 'max' => 79, 'label' => 'Unreliable / Mixed Record'],
    'D' => ['min' => 60, 'max' => 69, 'label' => 'Often Opposes Freedom'],
    'F' => ['min' => 0, 'max' => 59, 'label' => 'Failing the Constitution'],
]);

/* Global Grade Style Display Function
$args = [
    'type' => 'pill', //pill, text
    'grade' => 'A', //A-F
    'score' => 95, //0-100
    'size' => '36px', // size in px
    'fs' => '16px', // font size in px
]
*/
function fi_grade($args){
	$type = $args['type'] ?? 'pill'; //pill, text
	$grade = $args['grade'] ?? '';
	$score = $args['score'] ?? '';
	$size = $args['size'] ?? '36px';
	$font_size = $args['fs'] ?? '16px';

	//Determine if we received a grade or a score and assing grade for score
	if(!empty($score) && is_numeric($score)){
		foreach(FI_GRADES as $g => $data){
			if($score >= $data['min'] && $score <= $data['max']){
				$grade = $g;
				break;
			}
		}
	}
	$str = '<div class="fi-grade-'.$type.'" ';
	$str .= 'style="background:var(--fi-g-'.strtolower($grade).'); width:'.$size.'; height:'.$size.'; font-size:'.$font_size.';">';
	$str .= $grade.'</div>';

	return $str;
}

?>
<!--
INLINE STYLE FOR DEVELOPMENT: Consolidate when perfected.
-->
<style>
.fi-grade-pill {
	/*width: 2.25rem; height: 2.25rem;*/
	border-radius: 0.5rem;
	display: flex; align-items: center; justify-content: center;
	/*font-size: 0.9375rem; */
	font-weight: 800;
	flex-shrink: 0; color: #fff;
}
.fi-scale-range {
	font-size: 0.875rem; font-weight: 600;
	color: var(--bs-gray-800); min-width: 60px;
}
.fi-scale-desc {
	font-size: 0.875rem; color: var(--bs-gray-600); flex: 1;
}
#home-score-scale{
	max-width:340px;
}
</style>
<div id="home-method" class="container-fluid p-0 border-bottom bg-amber-light-1">
	<div class="container py-5">
		<div class="row g-0">
			<div class="col-12 col-md-6">
				<p class="text-uppercase text-primary fs-6">One number tells a story</p>
				<h2 class="ff-h fw-7 fs-3">What Is a Freedom Score?</h2>


<p class="fi-section-p" style="color:var(--blue-dark); font-weight:600;">
  The Constitution was designed to keep government small and your liberty big.
</p>
<p class="fi-section-p">
  Your Freedom Score shows how often your lawmaker voted to protect it — based on their actual votes, not speeches or promises.
</p>

<p>A voting record shows the pattern.</p>
<P>The Freedom Index scores every legislator against one standard: the Constitution. Not speeches. Not slogans. Not party labels. Recorded votes.</p>

				<p class="fi-section-p">
					A Freedom Score tells you how often your lawmaker voted to protect your rights, wallet, country, and independence.
				</p>
				<ul class="fi-grade-list">
					<li><strong>Your rights and your life.</strong><span class="fi-list-q"> Did they keep the government out of decisions that belong to you?</span></li>
					<li><strong>Your wallet.</strong><span class="fi-list-q"> Did they vote to stop overspending the country can&rsquo;t afford?</span></li>
					<li><strong>Your country.</strong><span class="fi-list-q"> Did they put America&rsquo;s interests ahead of foreign or globalist agendas?</span></li>
					<li><strong>Your independence.</strong><span class="fi-list-q"> Did they vote to keep America out of foreign wars, treaties, and entanglements that let outsiders control us?</span></li>
				</ul>
				<p class="fi-section-p mt-3">
					Every lawmaker swore an oath to uphold the Constitution. The score shows whether they kept it.
				</p>
				<a href="<?php echo esc_url( home_url( '/about/' ) ); ?>" class="btn btn-sm btn-outline-dark">Read the full methodology &rarr;</a>								
			</div>
			<div class="col-12 col-md-6">
				<div id="home-score-scale" class="card rounded-4 mx-auto">
					<div class="card-header rounded-top-4 bg-anchor">
						<div class="fs-7 fw-bold text-uppercase text-warm-5">Freedom Score Scale</div>
					</div>
					<div class="card-body">
						<ul class="list-unstyled list-flush">
						<?php
						foreach(FI_GRADES as $grade => $data) {
							echo '<li class="d-flex align-items-center gap-3 pb-2">
								'.fi_grade(['grade' => $grade, 'size' => '36px', 'fs' => '16px']).'
								<div class="fi-scale-range">'.$data['min'].'-'.$data['max'].'</div>
								<div class="fi-scale-desc">'.$data['label'].'</div>
							</li>';
						}
						?>
						</ul>
					</div>
				</div>
			</div>
		</div>
	</div>
</div>