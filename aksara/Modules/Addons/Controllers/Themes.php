<?php

namespace Aksara\Modules\Addons\Controllers;

/**
 * Addons > Themes Manager
 *
 * @author			Aby Dahana
 * @profile			abydahana.github.io
 * @website			www.aksaracms.com
 * @since			version 4.0.0
 * @copyright		(c) 2021 - Aksara Laboratory
 */

class Themes extends \Aksara\Laboratory\Core
{
	public function __construct()
	{
		parent::__construct();
		
		$this->restrict_on_demo();
		
		$this->set_permission(1);
		$this->set_theme('backend');
		
		helper('filesystem');
		
		$this->_primary								= service('request')->getGet('item');
	}
	
	public function index()
	{
		$this->set_title(phrase('theme_manager'))
		->set_icon('mdi mdi-palette')
		->set_output
		(
			array
			(
				'installed'							=> $this->_installed()
			)
		)
		
		->render();
	}
	
	/**
	 * Get theme detail
	 */
	public function detail()
	{
		$package									= json_decode(file_get_contents(ROOTPATH . 'themes' . DIRECTORY_SEPARATOR . $this->_primary . DIRECTORY_SEPARATOR . 'package.json'));
		
		if($package)
		{
			$package->folder						= $this->_primary;
			$package->integrity						= sha1($package->folder . ENCRYPTION_KEY . get_userdata('session_generated'));
		}
		
		$this->set_title(phrase('theme_detail'))
		->set_icon('mdi mdi-palette')
		->set_output
		(
			array
			(
				'detail'							=> $package
			)
		)
		->modal_size('modal-xl')
		
		->render();
	}
	
	/**
	 * Activate theme
	 */
	public function activate()
	{
		$this->permission->must_ajax(current_page('../'));
		
		if(!service('request')->getPost('theme'))
		{
			$html									= '
				<div class="p-3">
					<form action="' . current_page() . '" method="POST" class="--validate-form">
						<div class="text-center">
							' . phrase('are_you_sure_want_to_activate_this_theme') . '
						</div>
						<hr class="row" />
						<div class="--validation-callback mb-0"></div>
						<div class="row">
							<div class="col-6">
								<a href="javascript:void(0)" data-dismiss="modal" class="btn btn-light btn-block">
									<i class="mdi mdi-window-close"></i>
									' . phrase('cancel') . '
								</a>
							</div>
							<div class="col-6">
								<input type="hidden" name="theme" value="' . $this->_primary . '" />
								<button type="submit" class="btn btn-primary btn-block">
									<i class="mdi mdi-check"></i>
									' . phrase('activate') . '
								</button>
							</div>
						</div>
					</form>
				</div>
			';
			
			return make_json
			(
				array
				(
					'status'						=> 200,
					'meta'							=> array
					(
						'title'						=> phrase('action_warning'),
						'icon'						=> 'mdi mdi-alert-outline',
						'popup'						=> true
					),
					'html'							=> $html
				)
			);
		}
		
		if(DEMO_MODE)
		{
			return throw_exception(404, phrase('changes_will_not_saved_in_demo_mode'), current_page('../', array('item' => null)));
		}
		else if(!file_exists(ROOTPATH . 'themes' . DIRECTORY_SEPARATOR . service('request')->getPost('theme') . DIRECTORY_SEPARATOR . 'package.json'))
		{
			return throw_exception(404, phrase('no_theme_package_manifest_were_found'), current_page('../', array('item' => null)));
		}
		
		$package									= json_decode(file_get_contents(ROOTPATH . 'themes' . DIRECTORY_SEPARATOR . service('request')->getPost('theme') . DIRECTORY_SEPARATOR . 'package.json'));
		
		if(!$package || !isset($package->type) || !in_array($package->type, array('backend', 'frontend')))
		{
			return throw_exception(403, phrase('unable_to_activate_the_theme_with_invalid_package_manifest'), current_page('../', array('item' => null)));
		}
		
		if('backend' == $package->type)
		{
			$target									= 'backend_theme';
		}
		else
		{
			$target									= 'frontend_theme';
		}
		
		$site_id									= get_setting('id');
		
		$query										= $this->model->update
		(
			'app__settings',
			array
			(
				$target								=> service('request')->getPost('theme')
			),
			array
			(
				'id'								=> $site_id
			)
		);
		
		if($query)
		{
			return throw_exception(301, phrase('the_selected_theme_was_successfully_activated'), current_page('../', array('item' => null)));
		}
		
		return throw_exception(403, phrase('unable_to_activate_the_selected_theme'), current_page('../', array('item' => null)));
	}
	
	/**
	 * Customize theme
	 */
	public function customize()
	{
		if(!file_exists(ROOTPATH . 'themes' . DIRECTORY_SEPARATOR . $this->_primary . DIRECTORY_SEPARATOR . 'package.json'))
		{
			return throw_exception(404, phrase('no_theme_package_manifest_were_found'), current_page('../', array('item' => null)));
		}
		
		$package									= json_decode(file_get_contents(ROOTPATH . 'themes' . DIRECTORY_SEPARATOR . $this->_primary . DIRECTORY_SEPARATOR . 'package.json'));
		
		if(!$package)
		{
			return throw_exception(403, phrase('unable_to_customize_the_theme_with_invalid_package_manifest'), current_page('../', array('item' => null)));
		}
		
		$package->folder							= $this->_primary;
		$package->integrity							= sha1($package->folder . ENCRYPTION_KEY . get_userdata('session_generated'));
		
		if($this->valid_token(service('request')->getPost('_token')))
		{
			if(DEMO_MODE)
			{
				return throw_exception(404, phrase('changes_will_not_saved_in_demo_mode'), current_page('../', array('item' => null)));
			}
			else if(!is_writable(ROOTPATH . 'themes' . DIRECTORY_SEPARATOR . $package->folder . DIRECTORY_SEPARATOR . 'package.json'))
			{
				return throw_exception(400, array('colorscheme' => ROOTPATH . 'themes' . DIRECTORY_SEPARATOR . $package->folder . DIRECTORY_SEPARATOR . 'package.json ' . phrase('is_not_writable')));
			}
			
			$package->colorscheme					= service('request')->getPost('colorscheme');
			$folder									= $package->folder;
			
			unset($package->folder, $package->integrity);
			
			if(file_put_contents(ROOTPATH . 'themes' . DIRECTORY_SEPARATOR . $folder . DIRECTORY_SEPARATOR . 'package.json', json_encode($package)))
			{
				return throw_exception(301, phrase('the_theme_was_successfully_customized'), current_page('../', array('item' => null)));
			}
			
			return throw_exception(400, array('colorscheme' => ROOTPATH . 'themes' . DIRECTORY_SEPARATOR . $folder . DIRECTORY_SEPARATOR . 'package.json ' . phrase('is_not_writable')));
		}
		
		$this->set_title(phrase('theme_customization'))
		->set_icon('mdi mdi-palette')
		->set_output
		(
			array
			(
				'writable'							=> (is_writable(ROOTPATH . 'themes' . DIRECTORY_SEPARATOR . $package->folder . DIRECTORY_SEPARATOR . 'package.json') ? true : false),
				'detail'							=> $package
			)
		)
		->modal_size('modal-xl')
		
		->render();
	}
	
	/**
	 * Import theme
	 */
	public function import()
	{
		if(DEMO_MODE)
		{
			return throw_exception(404, phrase('changes_will_not_saved_in_demo_mode'), current_page('../'));
		}
		
		if($this->valid_token(service('request')->getPost('_token')))
		{
			$this->form_validation->setRule('file', phrase('theme_package'), 'max_size[file,' . MAX_UPLOAD_SIZE . ']|mime_in[file,application/zip,application/octet-stream,application/x-zip-compressed,multipart/x-zip]|ext_in[file,zip]');
			
			if($this->form_validation->run(service('request')->getPost()) === false)
			{
				return throw_exception(400, $this->form_validation->getErrors());
			}
			else if(empty($_FILES['file']['tmp_name']))
			{
				return throw_exception(400, array('file' => phrase('no_theme_package_were_chosen')));
			}
			else if(!class_exists('ZipArchive'))
			{
				return throw_exception(400, array('file' => phrase('no_zip_extension_found_on_your_web_server_configuration')));
			}
			
			$zip									= new \ZipArchive();
			$unzip									= $zip->open($_FILES['file']['tmp_name']);
			$tmp_path								= WRITEPATH . 'cache' . DIRECTORY_SEPARATOR . sha1($_FILES['file']['tmp_name']);
			
			if($unzip === true)
			{
				if(!is_dir($tmp_path) && !mkdir($tmp_path, 0755, true))
				{
					return throw_exception(400, array('file' => phrase('unable_to_extract_your_theme_package')));
				}
				
				$zip->extractTo($tmp_path);
				
				$files								= directory_map($tmp_path);
				
				if(!$files)
				{
					$zip->close();
					
					return throw_exception(400, array('file' => phrase('unable_to_extract_your_theme_package')));
				}
				
				$valid_package						= false;
				$package_path						= null;
				$extract							= false;
				
				foreach($files as $key => $val)
				{
					if(!$package_path)
					{
						$package_path				= str_replace('\\', null, $key);
					}
					
					if(!is_array($val)) continue;
					
					foreach($val as $_key => $_val)
					{
						if('package.json' == $_val && file_exists($tmp_path . DIRECTORY_SEPARATOR . $key . $_val))
						{
							$package				= json_decode(file_get_contents($tmp_path . DIRECTORY_SEPARATOR . $key . $_val));
							
							if(!$package || !isset($package->name) || !isset($package->description) || !isset($package->version) || !isset($package->author) || !isset($package->compatibility) || !isset($package->type) || !in_array($package->type, array('backend', 'frontend')))
							{
								$this->_rmdir($tmp_path);
								
								$zip->close();
								
								return throw_exception(400, array('file' => phrase('the_package_manifest_was_invalid')));
							}
							else if(!in_array(aksara('version'), $package->compatibility))
							{
								$this->_rmdir($tmp_path);
								
								$zip->close();
								
								return throw_exception(400, array('file' => phrase('this_theme_package_is_not_compatible_with_your_current_aksara_version')));
							}
							
							$valid_package			= true;
						}
					}
				}
				
				$this->_rmdir($tmp_path);
				
				if(!$valid_package)
				{
					$zip->close();
					
					return throw_exception(400, array('file' => phrase('no_package_manifest_found_on_your_theme_package')));
				}
				
				if(is_dir(ROOTPATH . 'themes' . DIRECTORY_SEPARATOR . $package_path) && !service('request')->getPost('upgrade'))
				{
					$zip->close();
					
					return throw_exception(400, array('theme' => phrase('the_theme_package_with_same_structure_is_already_installed')));
				}
				
				if(is_writable(ROOTPATH . 'themes'))
				{
					$extract						= $zip->extractTo(ROOTPATH . 'themes');
					
					$zip->close();
				}
				else
				{
					// get the site id
					$site_id						= get_setting('id');
					
					$query							= $this->model->get_where
					(
						'app__ftp',
						array
						(
							'site_id'				=> $site_id
						),
						1
					)
					->row();
					
					if(!$query)
					{
						return throw_exception(404, phrase('you_need_to_set_up_an_ftp_connection_to_update_your_core_system_due_the_server_does_not_appear_to_be_writable'), go_to('../../ftp'));
					}
					
					/* configuration found, decrypt password */
					$query->password				= service('encrypter')->decrypt(base64_decode($query->password));
					
					// try to connect to FTP
					$connection						= @ftp_connect($query->hostname, $query->port, 10);
					
					if(!$connection || !@ftp_login($connection, $query->username, $query->password))
					{
						return throw_exception(403, phrase('unable_to_connect_to_the_ftp_using_the_provided_configuration'));
					}
					
					$extract						= $zip->extractTo(ROOTPATH . 'themes');
					
					$zip->close();
				}
				
				if($extract && is_dir(ROOTPATH . 'themes' . DIRECTORY_SEPARATOR . $package_path))
				{
					return throw_exception(301, phrase('your_theme_package_was_successfully_imported'), current_page('../'));
				}
				else
				{
					return throw_exception(400, array('file' => phrase('your_theme_folder_seems_cannot_be_writable')));
				}
			}
			
			return throw_exception(400, array('file' => phrase('unable_to_extract_the_theme_package')));
		}
		
		$this->set_title(phrase('theme_importer'))
		->set_icon('mdi mdi-import')
		->render();
	}
	
	/**
	 * Delete theme
	 */
	public function delete()
	{
		$this->permission->must_ajax(current_page('../'));
		
		/* delete confirmation */
		if(!service('request')->getPost('theme'))
		{
			$html									= '
				<div class="p-3">
					<form action="' . current_page() . '" method="POST" class="--validate-form">
						<div class="text-center">
							' . phrase('are_you_sure_want_to_delete_this_theme') . '
						</div>
						<hr class="row" />
						<div class="--validation-callback mb-0"></div>
						<div class="row">
							<div class="col-6">
								<a href="javascript:void(0)" data-dismiss="modal" class="btn btn-light btn-block">
									<i class="mdi mdi-window-close"></i>
									' . phrase('cancel') . '
								</a>
							</div>
							<div class="col-6">
								<input type="hidden" name="theme" value="' . $this->_primary . '" />
								<button type="submit" class="btn btn-danger btn-block">
									<i class="mdi mdi-check"></i>
									' . phrase('continue') . '
								</button>
							</div>
						</div>
					</form>
				</div>
			';
			
			return make_json
			(
				array
				(
					'status'						=> 200,
					'meta'							=> array
					(
						'title'						=> phrase('action_warning'),
						'icon'						=> 'mdi mdi-alert-outline',
						'popup'						=> true
					),
					'html'							=> $html
				)
			);
		}
		
		$this->form_validation->setRule('theme', phrase('theme'), 'required');
		
		if($this->form_validation->run(service('request')->getPost()) === false)
		{
			return throw_exception(400, array('theme' => $this->form_validation->getErrors()));
		}
		
		/* check if requested theme to delete is match */
		if(service('request')->getPost('theme') && is_dir(ROOTPATH . 'themes' . DIRECTORY_SEPARATOR . service('request')->getPost('theme')))
		{
			if(DEMO_MODE)
			{
				return throw_exception(400, array('theme' => phrase('changes_will_not_saved_in_demo_mode')));
			}
			
			/* check if theme property is exists */
			if(file_exists(ROOTPATH . 'themes' . DIRECTORY_SEPARATOR . service('request')->getPost('theme') . DIRECTORY_SEPARATOR . 'package.json'))
			{
				$package							= json_decode(file_get_contents(ROOTPATH . 'themes' . DIRECTORY_SEPARATOR . service('request')->getPost('theme') . DIRECTORY_SEPARATOR . 'package.json'));
				
				if(!isset($package->type) || !in_array($package->type, array('backend', 'frontend')))
				{
					return throw_exception(400, array('theme' => phrase('unable_to_uninstall_theme_with_invalid_package')));
				}
				
				// get the site id
				$site_id							= get_setting('id');
				
				$active_theme						= $this->model->get_where
				(
					'app__settings',
					array
					(
						'id'						=> $site_id
					),
					1
				)
				->row($package->type . '_theme');
				
				if(service('request')->getPost('theme') == $active_theme)
				{
					return throw_exception(400, array('theme' => phrase('unable_to_uninstall_the_theme_that_is_in_use')));
				}
				
				/* delete theme */
				$this->_rmdir(ROOTPATH . 'themes' . DIRECTORY_SEPARATOR . service('request')->getPost('theme'));
			}
			else
			{
				/* theme property is not found */
				return throw_exception(400, array('theme' => phrase('a_theme_without_package_manifest_cannot_be_uninstall_from_the_theme_manager')));
			}
		}
		else
		{
			return throw_exception(400, array('theme' => phrase('the_theme_you_would_to_delete_is_not_exists_or_already_uninstalled')));
		}
		
		return throw_exception(301, phrase('the_selected_theme_was_successfully_uninstalled'), current_page('../', array('item' => null)));
	}
	
	/**
	 * List installed themes
	 */
	private function _installed()
	{
		$data										= directory_map(ROOTPATH . 'themes');
		
		if(!$data) return false;
		
		$output										= array();
		
		foreach($data as $key => $val)
		{
			if(is_array($val))
			{
				foreach($val as $_key => $_val)
				{
					if($_val != 'package.json') continue;
					
					$package						= json_decode(file_get_contents(ROOTPATH . 'themes' . DIRECTORY_SEPARATOR . $key . $_val));
					
					if($package)
					{
						$package->folder			= str_replace(DIRECTORY_SEPARATOR, null, $key);
						$package->integrity			= sha1($package->folder . ENCRYPTION_KEY . get_userdata('session_generated'));
						
						$output[]					= $package;
					}
				}
			}
		}
		
		return $output;
	}
	
	/**
	 * Remove directory recursivelly using
	 */
	private function _rmdir($directory = null)
	{
		if(is_dir($directory))
		{
			/* migration error, delete directory */
			if(!delete_files($directory, true))
			{
				/* Unable to delete directory. Get FTP configuration */
				$site_id							= get_setting('id');
				
				$query								= $this->model->get_where
				(
					'app__ftp',
					array
					(
						'site_id'					=> $site_id
					),
					1
				)
				->row();
				
				if($query)
				{
					/* configuration found, decrypt password */
					$query->password				= service('encrypter')->decrypt(base64_decode($query->password));
					
					/* trying to delete directory using ftp instead */
					$connection						= @ftp_connect($query->hostname, $query->port, 10);
					
					if($connection && @ftp_login($connection, $query->username, $query->password))
					{
						/* yay! FTP is connected, try to delete directory */
						$this->_ftp_rmdir($connection, $directory);
						
						/* close FTP connection */
						ftp_close($connection);
					}
				}
			}
			
			@rmdir($directory);
		}
	}
	
	/**
	 * Remove directory and its files using FTP
	 */
	private function _ftp_rmdir($connection = null, $directory = null)
	{
		if(!$directory)
		{
			return false;
		}
		
		$lists										= ftp_mlsd($connection, $directory);
		
		unset($lists[0]);
		unset($lists[1]);

		foreach($lists as $list)
		{
			$full									= $directory . DIRECTORY_SEPARATOR . $list['name'];
			
			if($list['type'] == 'dir')
			{
				// directory found, reinitialize
				$this->_ftp_rmdir($connection, $full);
			}
			else
			{
				// delete file
				ftp_delete($connection, $full);
			}
		}
		
		// delete directory
		ftp_rmdir($connection, $directory);
	}
}
