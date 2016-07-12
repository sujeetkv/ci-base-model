<?php defined('BASEPATH') OR exit('No direct script access allowed');
/**
 * CodeIgniter Base Model
 *
 * @author Sujeet <sujeetkv90@gmail.com>
 * @link https://github.com/sujeet-kumar/ci-base-model
 */

class MY_Model extends CI_Model
{
	/**
	 * model level db object
	 */
	public $model_db;
	
	protected $table = NULL;
	protected $primary_key = NULL;
	
	protected $has_many = array();
	protected $belongs_to = array();
	
	protected $relatives = array();
	protected $recursive_level = 0;
	
	protected $default_return_type = 'object';
	private $_return_type = NULL;
	
	protected $sync_timezone = true;
	
	/**
	 * model constructor
	 */
	public function __construct(){
		parent::__construct();
		
		$this->load->helper('inflector');
		
		if(get_class($this) != get_class()){
			$this->sync_timezone and $this->_syncTimezone();
			
			/* to prevent cross model interference of db object use this
			   $this->model_db = clone $this->db;
			   (it will affect some features e.g. query profiling) */
			$this->model_db = $this->db;
			
			$this->_setTableName();
			$this->_setPrimaryKey();
		}
	}
	
	/**
	 * Get single record
	 *
	 * @param	mixed	$condition
	 * @param	mixed	$fields
	 * @param	string	$table
	 * @param	array	$options
	 */
	public function getOne($condition = '', $fields = '', $table = '', $options = NULL){
		empty($condition) or $this->model_db->where($condition);
		if(! empty($fields)){
			is_array($fields) or $fields = array($fields);
			call_user_func_array(array($this->model_db, 'select'), $fields);
		}
		is_array($options) and $this->_execOptions($options);
		$row = $this->model_db->get($this->getTable($table))->{$this->_returnType()}();
		
		if($this->recursive_level > 0 or $this->recursive_level === true){
			$row = $this->relateRecursive($row, $table);
			$this->recursive_level = 0;
		}
		
		if(! empty($this->relatives)){
			$row = $this->relate($row, $table);
			$this->relatives = array();
		}
		
		return $row;
	}
	
	/**
	 * Get single record filtered by field value
	 *
	 * @param	string	$field
	 * @param	mixed	$value
	 * @param	mixed	$fields
	 * @param	string	$table
	 * @param	array	$options
	 */
	public function getOneBy($field, $value, $fields = '', $table = '', $options = NULL){
		return $this->getOne(array($field => $value), $fields, $table, $options);
	}
	
	/**
	 * Get single record filtered by id
	 *
	 * @param	int		$id
	 * @param	mixed	$fields
	 * @param	string	$table
	 * @param	array	$options
	 */
	public function getById($id, $fields = '', $table = '', $options = NULL){
		return $this->getOne(array($this->getTable($table).'.'.$this->primary_key => $id), $fields, $table, $options);
	}
	
	/**
	 * Get single field value
	 *
	 * @param	string	$field
	 * @param	mixed	$condition
	 * @param	string	$table
	 * @param	array	$options
	 */
	public function getField($field, $condition = '', $table = '', $options = NULL){
		if($row = $this->getOne($condition, $field, $table, $options)){
			return $row->$field;
		}else{
			return NULL;
		}
	}
	
	/**
	 * Get multiple records
	 *
	 * @param	mixed	$condition
	 * @param	mixed	$fields
	 * @param	string	$table
	 * @param	array	$options
	 */
	public function getAll($condition = '', $fields = '', $table = '', $options = NULL){
		empty($condition) or $this->model_db->where($condition);
		if(! empty($fields)){
			is_array($fields) or $fields = array($fields);
			call_user_func_array(array($this->model_db, 'select'), $fields);
		}
		is_array($options) and $this->_execOptions($options);
		$rows = $this->model_db->get($this->getTable($table))->{$this->_returnType(true)}();
		
		if($this->recursive_level > 0 or $this->recursive_level === true){
			foreach($rows as &$row){
				$row = $this->relateRecursive($row, $table);
			}
			$this->recursive_level = 0;
		}
		
		if(! empty($this->relatives)){
			foreach($rows as &$row){
				$row = $this->relate($row, $table);
			}
			$this->relatives = array();
		}
		
		return $rows;
	}
	
	/**
	 * Get multiple records filtered by field value
	 *
	 * @param	string	$field
	 * @param	mixed	$value
	 * @param	mixed	$fields
	 * @param	string	$table
	 * @param	array	$options
	 */
	public function getAllBy($field, $value, $fields = '', $table = '', $options = NULL){
		return $this->getAll(array($field => $value), $fields, $table, $options);
	}
	
	/**
	 * Set order of records
	 *
	 * @param	string	$orderby
	 * @param	string	$direction asc or desc
	 */
	public function order($orderby, $direction = ''){
		$this->model_db->order_by($orderby, $direction);
		return $this;
	}
	
	/**
	 * Set limit of records
	 *
	 * @param	int	$value
	 * @param	int	$offset
	 */
	public function limit($value, $offset = ''){
		$this->model_db->limit($value, $offset);
		return $this;
	}
	
	/**
	 * Set relative to be fetched with records
	 *
	 * @param	string	$relative
	 * @param	mixed	$fields
	 * @param	mixed	$limit
	 * @param	mixed	$order
	 * @param	array	$scope
	 */
	public function with($relative, $fields = '', $limit = array(), $order = array(), $scope = array()){
		is_array($limit) or $limit = array($limit);
		is_array($order) or $order = array($order);
		is_array($scope) or $scope = array();
		$this->relatives[$relative] = array($fields, $limit, $order, $scope);
		return $this;
	}
	
	/**
	 * Set relative level to be fetched with records recursively
	 *
	 * @param	int|bool	$level
	 */
	public function withRecursive($level = true){
		$this->recursive_level = $level === true ? $level : (int) $level;
		return $this;
	}
	
	/**
	 * Set return type records as array
	 */
	public function asArray(){
		$this->_return_type = 'array';
		return $this;
	}
	
	/**
	 * Set return type records as object
	 */
	public function asObject(){
		$this->_return_type = 'object';
		return $this;
	}
	
	/**
	 * Get record count
	 *
	 * @param	mixed	$condition
	 * @param	string	$table
	 * @param	array	$options
	 */
	public function countAll($condition = '', $table = '', $options = NULL){
		if(empty($condition)){
			return $this->model_db->count_all($this->getTable($table));
		}else{
			is_array($options) and $this->_execOptions($options);
			return $this->model_db->where($condition)->count_all_results($this->getTable($table));
		}
	}
	
	/**
	 * Get record count of field
	 *
	 * @param	string	$field
	 * @param	mixed	$condition
	 * @param	string	$table
	 * @param	array	$options
	 */
	public function countField($field, $condition = '', $table = '', $options = NULL){
		empty($condition) or $this->model_db->where($condition);
		is_array($options) and $this->_execOptions($options);
		$query = $this->model_db->select("COUNT($field) AS rowcount", false)->get($this->getTable($table));
		return ($query->num_rows() == 0) ? 0 : (int) $query->row()->rowcount;
	}
	
	/**
	 * Check if id exists
	 *
	 * @param	int		$id
	 * @param	string	$table
	 */
	public function hasId($id, $table = ''){
		return (bool) $this->countField($this->primary_key, array($this->primary_key => $id), $table);
	}
	
	/**
	 * Insert record
	 *
	 * @param	mixed	$data
	 * @param	string	$table
	 */
	public function create($data, $table = ''){
		$inserted = empty($data) ? false : $this->model_db->insert($this->getTable($table), $data);
		return ($inserted) ? $this->model_db->insert_id() : false;
	}
	
	/**
	 * Insert multiple records
	 *
	 * @param	mixed	$data
	 * @param	string	$table
	 */
	public function createMulti($data, $table = ''){
		return empty($data) ? false : $this->model_db->insert_batch($this->getTable($table), $data);
	}
	
	/**
	 * Update records
	 *
	 * @param	mixed	$condition
	 * @param	mixed	$data
	 * @param	string	$table
	 */
	public function update($condition, $data, $table = ''){
		return (!empty($data) and $this->model_db->where($condition)->update($this->getTable($table), $data));
	}
	
	/**
	 * Update record filtered by id
	 *
	 * @param	int 	$id
	 * @param	mixed	$data
	 * @param	string	$table
	 */
	public function updateById($id, $data, $table = ''){
		return $this->update(array($this->primary_key => $id), $data, $table);
	}
	
	/**
	 * Delete records
	 *
	 * @param	mixed	$condition
	 * @param	string	$table
	 */
	public function delete($condition, $table = ''){
		$this->model_db->where($condition);
		return $this->model_db->delete($this->getTable($table));
	}
	
	/**
	 * Delete record filtered by id
	 *
	 * @param	int 	$id
	 * @param	string	$table
	 */
	public function deleteById($id, $table = ''){
		return $this->delete(array($this->primary_key => $id), $table);
	}
	
	/**
	 * Before save callback signature
	 *
	 * @param	mixed 	$data
	 * @param	string	$table
	 */
	public function beforeSave($data, $table){
		// to be overridden
		return $data;
	}
	
	/**
	 * Insert or Update record
	 *
	 * @param	mixed	$data
	 * @param	string	$table
	 */
	public function save($data, $table = ''){
		$data = $this->beforeSave($data, $table);
		
		if(empty($data)){
			return false;
		}
		
		$fields = $this->getFieldTypes($table);
		$time_fields = array_intersect(array('created', 'modified'), array_keys($fields));
		
		if(is_object($data)){
			if(isset($data->{$this->primary_key})){
				$id = $data->{$this->primary_key};
				unset($data->{$this->primary_key});
				
				if(isset($data->modified)){
					if($data->modified === false){
						unset($data->modified);
					}
				}elseif(in_array('modified', $time_fields) and in_array($fields['modified'], array('datetime', 'timestamp'))){
					$data->modified = date('Y-m-d H:i:s');
				}
				
				return $this->updateById($id, $data, $table);
			}else{
				if(!isset($data->created) and in_array('created', $time_fields) 
				and in_array($fields['created'], array('datetime', 'timestamp'))){
					$data->created = date('Y-m-d H:i:s');
				}
				return $this->create($data, $table);
			}
		}else{
			if(isset($data[$this->primary_key])){
				$id = $data[$this->primary_key];
				unset($data[$this->primary_key]);
				
				if(isset($data['modified'])){
					if($data['modified'] === false){
						unset($data['modified']);
					}
				}elseif(in_array('modified', $time_fields) and in_array($fields['modified'], array('datetime', 'timestamp'))){
					$data['modified'] = date('Y-m-d H:i:s');
				}
				return $this->updateById($id, $data, $table);
			}else{
				if(!isset($data['created']) and in_array('created', $time_fields) 
				and in_array($fields['created'], array('datetime', 'timestamp'))){
					$data['created'] = date('Y-m-d H:i:s');
				}
				return $this->create($data, $table);
			}
		}
	}
	
	/**
	 * Get table name of model
	 */
	public function getTable($table = ''){
		return (!empty($table)) ? $table : $this->table;
	}
	
	/**
	 * Get primary key name of model
	 */
	public function getPrimaryKey(){
		return $this->primary_key;
	}
	
	/**
	 * Get schema of table
	 *
	 * @param	string	$table
	 */
	public function getSchema($table = ''){
		return $this->model_db->field_data($this->getTable($table));
	}
	
	/**
	 * Get fields of table
	 *
	 * @param	string	$table
	 */
	public function getFields($table = ''){
		return $this->model_db->list_fields($this->getTable($table));
	}
	
	/**
	 * Check if field exists
	 *
	 * @param	string	$field
	 * @param	string	$table
	 */
	public function hasField($field, $table = ''){
		return $this->model_db->field_exists($field, $this->getTable($table));
	}
	
	/**
	 * Get field types of table
	 *
	 * @param	string	$table
	 */
	public function getFieldTypes($table = ''){
		$types = array();
		if($fields = $this->getSchema($table)){
			foreach($fields as $field){
				$types[$field->name] = $field->type;
			}
		}
		return $types;
	}
	
	/**
	 * Get field type
	 *
	 * @param	string	$field
	 * @param	string	$table
	 */
	public function getFieldType($field, $table = ''){
		$type = NULL;
		if($types = $this->getFieldTypes($table)){
			isset($types[$field]) and $type = $types[$field];
		}
		return $type;
	}
	
	/**
	 * Get next id of table
	 *
	 * @param	string	$table
	 */
	public function getNextId($table = ''){
		if(! in_array($this->model_db->platform(), array('mysql', 'mysqli'))){
			return NULL;
		}else{
			/* return (int) $this->model_db->select('AUTO_INCREMENT')
							->where('TABLE_NAME', $this->model_db->dbprefix($this->getTable($table)))
							->where('TABLE_SCHEMA', $this->model_db->database)
							->get('information_schema.TABLES')
							->row()->AUTO_INCREMENT; */
			$query = "SHOW TABLE STATUS WHERE `Name` = '".$this->model_db->dbprefix($this->getTable($table))."'";
			return ($row = $this->model_db->query($query)->row()) ? (int) $row->Auto_increment : NULL;
		}
    }
	
	/**
	 * Fetch primary key name of table
	 *
	 * @param	string	$table
	 */
	public function fetchPrimaryKey($table = ''){
		$key = NULL;
		
		if(! in_array($this->model_db->platform(), array('mysql', 'mysqli'))){
			if($fields = $this->getSchema($this->getTable($table))){
				foreach($fields as $field){
					if($field->primary_key == '1'){
						$key = $field->name;
						break;
					}
				}
			}
		}else{
			$query = "SHOW KEYS FROM `".$this->model_db->dbprefix($this->getTable($table))."` WHERE `Key_name` = 'PRIMARY'";
			if($row = $this->model_db->query($query)->row()){
				$key = $row->Column_name;
			}
		}
		
		return $key;
	}
	
	
	protected function relate($row, $table = ''){
		if(! empty($row)){
			foreach($this->has_many as $key => $value){
				if(is_string($value)){
					$relative = $value;
					$options = array();
				}else{
					$relative = $key;
					$options = $value;
				}
				
				isset($options['foreign_key']) or $options['foreign_key'] = singular($this->getTable($table)) . '_id';
				
				$relative_model = isset($options['model']) ? $options['model'] : singular($relative) . '_model';
				
				$fields = isset($options['fields']) ? $options['fields'] : '';
				
				$limit = NULL;
				if(isset($options['limit'])){
					$limit = is_array($options['limit']) ? $options['limit'] : array($options['limit']);
				}
				
				$order = NULL;
				if(isset($options['order'])){
					$order = is_array($options['order']) ? $options['order'] : array($options['order']);
				}
				
				$condition = (isset($options['scope']) and is_array($options['scope'])) ? $options['scope'] : array();
				
				if(array_key_exists($relative, $this->relatives)){
					$this->load->model($relative_model);
					
					empty($this->relatives[$relative][0]) or $fields = $this->relatives[$relative][0];
					
					empty($this->relatives[$relative][1]) or $limit = $this->relatives[$relative][1];
					empty($limit) or call_user_func_array(array($this->$relative_model, 'limit'), $limit);
					
					empty($this->relatives[$relative][2]) or $order = $this->relatives[$relative][2];
					empty($order) or call_user_func_array(array($this->$relative_model, 'order'), $order);
					
					$condition = array_merge($condition, $this->relatives[$relative][3]);
					
					if(is_object($row)){
						$condition[$options['foreign_key']] = $row->{$this->primary_key};
						$row->{$relative} = $this->$relative_model->getAll($condition, $fields);
					}else{
						$condition[$options['foreign_key']] = $row[$this->primary_key];
						$row[$relative] = $this->$relative_model->asArray()->getAll($condition, $fields);
					}
				}
			}
			
			foreach($this->belongs_to as $key => $value){
				if(is_string($value)){
					$relative = $value;
					$options = array();
				}else{
					$relative = $key;
					$options = $value;
				}
				
				isset($options['foreign_key']) or $options['foreign_key'] = singular($relative) . '_id';
				
				$relative_model = isset($options['model']) ? $options['model'] : singular($relative) . '_model';
				
				$fields = isset($options['fields']) ? $options['fields'] : '';
				
				if(array_key_exists($relative, $this->relatives)){
					$this->load->model($relative_model);
					
					empty($this->relatives[$relative][0]) or $fields = $this->relatives[$relative][0];
					
					if(is_object($row)){
						$row->{$relative} = $this->$relative_model->getById($row->{$options['foreign_key']}, $fields);
					}else{
						$row[$relative] = $this->$relative_model->asArray()->getById($row[$options['foreign_key']], $fields);
					}
				}
			}
		}
		
		return $row;
	}
	
	protected function relateRecursive($row, $table = ''){
		if(! empty($row)){
			foreach($this->has_many as $key => $value){
				if(is_string($value)){
					$relative = $value;
					$options = array();
				}else{
					$relative = $key;
					$options = $value;
				}
				
				isset($options['foreign_key']) or $options['foreign_key'] = singular($this->getTable($table)) . '_id';
				
				$relative_model = isset($options['model']) ? $options['model'] : singular($relative) . '_model';
				
				$this->load->model($relative_model);
				
				$fields = isset($options['fields']) ? $options['fields'] : '';
				
				if(isset($options['limit'])){
					$limit = is_array($options['limit']) ? $options['limit'] : array($options['limit']);
					call_user_func_array(array($this->$relative_model, 'limit'), $limit);
				}
				
				if(isset($options['order'])){
					$order = is_array($options['order']) ? $options['order'] : array($options['order']);
					call_user_func_array(array($this->$relative_model, 'order'), $order);
				}
				
				$condition = (isset($options['scope']) and is_array($options['scope'])) ? $options['scope'] : array();
				
				$level = $this->recursive_level === true ? $this->recursive_level : $this->recursive_level - 1;
				
				if(is_object($row)){
					$condition[$options['foreign_key']] = $row->{$this->primary_key};
					$row->{$relative} = $this->$relative_model->withRecursive($level)->getAll($condition, $fields);
				}else{
					$condition[$options['foreign_key']] = $row[$this->primary_key];
					$row[$relative] = $this->$relative_model->withRecursive($level)->asArray()->getAll($condition, $fields);
				}
			}
		}
		return $row;
	}
	
	private function _execOptions($options){
		foreach($options as $option => $params){
			if(! method_exists($this->model_db, $option) or preg_match('/^get/i', $option)){
				$trace = debug_backtrace();
				show_error(
					'Invalid options parameter \''.$option.'\' for '.get_class($this).'::'.$trace[1]['function'].'()',
					500,
					'A Model Error Occurred'
				);
			}else{
				is_array($params) or $params = array($params);
				call_user_func_array(array($this->model_db, $option), $params);
			}
		}
	}
	
	private function _returnType($multi = false){
		$return_type = $this->default_return_type;
		if(! empty($this->_return_type)){
			$return_type = $this->_return_type;
			$this->_return_type = NULL;
		}
		$method = $multi ? 'result' : 'row';
		return ($return_type == 'array') ? $method . '_array' : $method;
	}
	
	private function _setTableName(){
		if(empty($this->table) and $this->table !== false){
			$this->table = plural(preg_replace('/(_model|_m)?$/', '', strtolower(get_class($this))));
		}
	}
	
	private function _setPrimaryKey(){
		if(empty($this->primary_key)){
			if(empty($this->table)){
				show_error(
					'Set \'primary_key\' to use key dependent methods of \''.get_class($this).'\'',
					500,
					'A Model Error Occurred'
				);
			}
			
			$this->primary_key = $this->fetchPrimaryKey();
		}
	}
	
	private function _syncTimezone(){
		in_array($this->db->platform(), array('mysql', 'mysqli')) 
		and $this->db->query("SET time_zone = '".date('P')."'");
	}
}

/* End of file MY_Model.php */
/* Location: ./application/core/MY_Model.php */