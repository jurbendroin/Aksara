<div class="leading pt-5 pb-5 mb-5 bg-light relative" style="background: url(<?php echo (isset($category->category_image) && $category->category_image != 'placeholder.png' ? get_image('blogs', $category->category_image) : get_image('blogs', 'cover.jpg')); ?>) center center no-repeat; background-size: cover">
	<div class="clip gradient-top"></div>
	<div class="container pt-5 pb-5">
		<div class="row">
			<div class="col-md-8 offset-md-2">
				<h1 class="text-center text-light">
					<?php echo $meta->title; ?>
				</h1>
				<p class="lead text-center text-light mb-5">
					<?php echo $meta->description; ?>
				</p>
			</div>
		</div>
		<div class="row">
			<div class="col-md-8 offset-md-2 col-lg-6 offset-lg-3">
				<form action="<?php echo base_url('blogs/search', array('category' => (service('request')->uri->getTotalSegments() > 1 ? service('request')->uri->getSegment(2) : null), 'per_page' => null)); ?>" method="POST" class="form-horizontal relative --xhr-form">
					<input type="text" name="q" class="form-control form-control-lg pt-4 pr-4 pb-4 pl-4 border-0 rounded-pill" placeholder="<?php echo phrase('search_post_under') . ' ' . $meta->title; ?>" />
					<button type="submit" class="btn btn-lg float-right absolute top right">
						<i class="mdi mdi-magnify font-weight-bold"></i>
					</button>
				</form>
			</div>
		</div>
	</div>
</div>

<div class="container">
	<div class="row">
		<?php
			if($results)
			{
				foreach($results as $key => $val)
				{
					$item_tags					= array_map('trim', explode(',', $val->post_tags));
					$tags						= null;
					
					if(sizeof($item_tags) > 0)
					{
						foreach($item_tags as $label => $badge)
						{
							if($label == 2) break;
							if($badge)
							{
								$tags			.= '
									<a href="' . go_to('../tags', array('q' => $badge)) . '" class="--xhr">
										<span class="badge badge-secondary mr-2">
											#' . trim($badge) . '
										</span>
									</a>
								';
							}
						}
					}
					
					echo '
						<div class="col-sm-2 col-md-4 col-lg-3">
							<div class="card border-0 rounded-more shadow-sm mb-5">
								<a href="' . base_url(array('blogs', $val->category_slug, $val->post_slug)) . '" class="--xhr d-block">
									<div class="relative rounded-top" style="background:url(' . get_image('blogs', $val->featured_image, 'thumb') . ') center center no-repeat; background-size: cover; height: 200px">
										<div class="clip gradient-top rounded-top"></div>
										<div class="absolute bottom p-3">
											<h5 class="text-light">
												' . truncate($val->post_title, 80) . '
											</h5>
										</div>
									</div>
								</a>
								<div class="card-body">
									<p class="card-text">
										<a href="' . base_url(array('blogs', $val->category_slug, $val->post_slug)) . '" class="--xhr d-block">
											' . truncate($val->post_excerpt, 100) . '
										</a>
									</p>
									<p class="card-text">
										<i class="mdi mdi-clock-outline"></i> ' . time_ago($val->updated_timestamp) . '
									</p>
								</div>
							</div>
						</div>
					';
				}
				
				echo '
					<div class="col-lg-12">
						' . $template->pagination . '
					</div>
				';
			}
			else
			{
				echo '
					<div class="col-lg-6 offset-lg-3 text-center">
						<div class="pt-5 pb-5">
							<i class="mdi mdi-dropbox mdi-5x text-muted"></i>
							<h2>
								' . phrase('the_category_does_not_have_any_post') . '
							</h2>
						</div>
					</div>
				';
			}
		?>
	</div>
</div>
