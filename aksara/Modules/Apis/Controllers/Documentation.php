<?php

namespace Aksara\Modules\Apis\Controllers;

/**
 * APIs > Documentation
 *
 * @author			Aby Dahana
 * @profile			abydahana.github.io
 * @website			www.aksaracms.com
 * @since			version 4.2.8
 * @copyright		(c) 2021 - Aksara Laboratory
 */

class Documentation extends \Aksara\Laboratory\Core
{
	private $_collection							= array();
	private $_namespace								= array();
	
	public function __construct()
	{
		parent::__construct();
		
		$this->set_theme('backend');
		
		$this->_primary								= service('request')->getGet('slug');
		
		if($this->_primary && 'fetch' == service('request')->getPost('mode'))
		{
			return $this->_fetch_properties($this->_primary, service('request')->getPost('group'));
		}
	}
	
	public function index()
	{
		$this->set_title(phrase('api_documentation'))
		->set_icon('mdi mdi-book-open-page-variant')
		
		->set_output
		(
			array
			(
				'modules'							=> $this->_scan_module(),
				'permission'						=> $this->_permission($this->_primary),
				'active'							=> $this->_primary
			)
		)
		
		->render();
	}
	
	private function _permission($slug = null)
	{
		$groups										= array
		(
			array
			(
				'group_id'							=> 0,
				'group_name'						=> phrase('public'),
				'group_description'					=> null,
				'group_privileges'					=> json_encode
				(
					array
					(
						$slug						=> array('index')
					)
				)
				
			)
		);
		
		$privileges									= array('index');
		
		if($slug)
		{
			$query									= $this->model->like
			(
				array
				(
					'group_privileges'				=> '"' . $slug . '"'
				)
			)
			->or_like
			(
				array
				(
					'group_privileges'				=> '"' . str_replace('/', '\/', $slug) . '"'
				)
			)
			->get_where
			(
				'app__groups',
				array
				(
					'status'						=> 1
				)
			)
			->result();
			
			if($query)
			{
				$groups								= $query;
			}
			
			$query									= $this->model->get_where
			(
				'app__groups_privileges',
				array
				(
					'path'							=> $slug
				),
				1
			)
			->row('privileges');
			
			if($query)
			{
				$privileges							= json_decode($query);
			}
		}
		
		return array
		(
			'groups'								=> $groups,
			'privileges'							=> $privileges
		);
	}
	
	private function _fetch_properties($slug = null, $group_id = 0)
	{
		if(in_array($slug, $this->_restricted_resource()))
		{
			return false;
		}
		
		$method										= service('request')->getPost('method');
		$title										= $slug;
		$output										= array();
		$session									= get_userdata();
		$restore_after								= false;
		$s											= 'success';
		$e											= 'error';
		
		if(!$slug || !$method)
		{
			return false;
		}
		
		if($group_id != get_userdata('group_id'))
		{
			$restore_after							= true;
			
			set_userdata('group_id', $group_id);
		}
		
		$exception									= array
		(
			'status'								=> phrase('http_status_code'),
			'message'								=> phrase('exception_message'),
			'target'								=> phrase('redirect_url')
		);
		
		$curl										= \Config\Services::curlrequest
		(
			array
			(
				'timeout'							=> 5,
				'http_errors'						=> false
			)
		);
		
		foreach($method as $key => $val)
		{
			$request								= $curl->get
			(
				base_url($slug . ('index' != $val ? '/' . $val : null)),
				array
				(
					'allow_redirects'				=> array
					(
						'max'						=> 2
					),
					'headers'						=> array
					(
						'X-API-KEY'					=> sha1(ENCRYPTION_KEY . session_id()),
						'X-ACCESS-TOKEN'			=> session_id()
					)
				)
			);
			
			$response								= json_decode($request->getBody());
			
			if(isset($response->results) && !in_array($response->method, array('index', 'read', 'delete', 'export', 'print', 'pdf')))
			{
				$output[$val]['parameter']			= $response->results;
			}
			
			if(isset($response->query_string) && !in_array($response->method, array('index', 'create')))
			{
				$output[$val]['query_string']		= $response->query_string;
			}
			
			$output[$val]['response'][$e]			= $exception;
			
			if(isset($response->method) && !in_array($response->method, array('create', 'update', 'delete')))
			{
				$output[$val]['response'][$s]		= $response;
			}
			else
			{
				$output[$val]['response'][$s]		= $exception;
				
				if(isset($response->method) && in_array($response->method, array('create', 'update')) && isset($response->results) && $response->results)
				{
					$error							= $exception;
					$error['message']				= array();
					
					foreach($response->results as $_key => $_val)
					{
						if(!$_val->required) continue;
						
						$error['message'][$_key]	= phrase('validation_message');
					}
					
					$error['status']				= 400;
					
					unset($error['target']);
					
					$output[$val]['response'][$e]	= $error;
				}
			}
		}
		
		if($restore_after && isset($session['group_id']))
		{
			set_userdata('group_id', $session['group_id']);
		}
		
		if(isset($output['export']))
		{
			$output['export']['response'][$s]		= phrase('binary_file');
		}
		if(isset($output['print']))
		{
			$output['print']['response'][$s]		= phrase('html_file');
		}
		if(isset($output['pdf']))
		{
			$output['pdf']['response'][$s]			= phrase('binary_file');
		}
		
		return make_json
		(
			array
			(
				'title'								=> $title,
				'results'							=> $output
			)
		);
	}
	
	private function _scan_module()
	{
		helper('filesystem');
		
		$modules									= array();
		$scandir									= array_merge(directory_map('..' . DIRECTORY_SEPARATOR . 'aksara' . DIRECTORY_SEPARATOR . 'Modules'), directory_map('..' . DIRECTORY_SEPARATOR . 'modules'));
		
		if($scandir)
		{
			foreach($scandir as $key => $val)
			{
				if(isset($val['Controllers' . DIRECTORY_SEPARATOR]) && is_array($val['Controllers' . DIRECTORY_SEPARATOR]))
				{
					$this->_scandir($key, $val['Controllers' . DIRECTORY_SEPARATOR]);
				}
			}
		}
		
		if($this->_collection)
		{
			sort($this->_collection);
		}
		
		return $this->_collection;
	}
	
	private function _scandir($parent_dir = null, $scandir = array(), $namespace = null)
	{
		foreach($scandir as $key => $val)
		{
			if(is_array($val))
			{
				$this->_scandir($parent_dir . (!is_numeric($key) ? $key : null), $val, $key);
			}
			else
			{
				$namespace							= $namespace . $val;
				$val								= '/' . str_replace(array('\\', '.php'), array('/', null), strtolower($parent_dir . (!is_numeric($key) ? $key : null) . $val));
				
				$find_duplicate						= array_reverse(explode('/', $val));
				
				$is_duplicate						= (isset($find_duplicate[0]) && isset($find_duplicate[1]) && $find_duplicate[0] == $find_duplicate[1] ? true : false);
				
				if(!$is_duplicate)
				{
					$slug							= ltrim(rtrim($val, '/'), '/');
				}
				else
				{
					$slug							= ltrim(rtrim('/' . str_replace(array('\\', '.php'), array('/', null), strtolower($parent_dir . (!is_numeric($key) ? $key : null))), '/'), '/');
				}
				
				if(!in_array($slug, $this->_restricted_resource()))
				{
					$this->_collection[]		= $slug;
					$this->_namespace[$slug]	= $namespace;
				}
			}
		}
	}
	
	private function _restricted_resource()
	{
		return array('administrative/updater', 'assets', 'assets/svg', 'pages/blank', 'shortlink', 'xhr', 'xhr/boot', 'xhr/language', 'xhr/partial', 'xhr/partial/account', 'xhr/partial/language', 'xhr/summernote');
	}
}
