<?php
namespace Yurun\Util\Swoole\PHPMailer;

abstract class ResourceMap
{
	/**
	 * resource对象hash => client对象
	 *
	 * @var array
	 */
	private static $map = [];

	/**
	 * scalar resource => resource object
	 *
	 * @var array
	 */
	private static $objectMap = [];

	/**
	 * not scalar resource => resource object
	 *
	 * @var array
	 */
	private static $otherMap = [];

	/**
	 * object key => resource object
	 *
	 * @var array
	 */
	private static $otherToObjectMap = [];

	/**
	 * 增加资源
	 *
	 * @param resource $resource
	 * @param mixed $object
	 * @return void
	 */
	public static function addResource($resource, $object)
	{
		static::setData($resource, 'object', $object);
	}

	/**
	 * 释放资源
	 *
	 * @param resource $resource
	 * @return void
	 */
	public static function releaseResource($resource)
	{
		$hash = spl_object_hash(static::parseObject($resource));
		if(isset(static::$map[$hash]))
		{
			\fclose($resource);
			unset(static::$map[$hash]);
		}
	}

	/**
	 * 获取对象
	 *
	 * @param resource $resource
	 * @return mixed
	 */
	public static function getObject($resource)
	{
		return static::getData($resource, 'object');
	}

	/**
	 * 设置数据
	 *
	 * @param resource $resource
	 * @param string $name
	 * @param mixed $value
	 * @return void
	 */
	public static function setData($resource, $name, $value)
	{
		$hash = spl_object_hash(static::parseObject($resource));
		static::$map[$hash][$name] = $value;
	}

	/**
	 * 获取数据
	 *
	 * @param resource $resource
	 * @param string $name
	 * @param mixed $default
	 * @return mixed
	 */
	public static function getData($resource, $name, $default = null)
	{
		$hash = spl_object_hash(static::parseObject($resource));
		return static::$map[$hash][$name] ?? $default;
	}

	/**
	 * 将非对象转为对象，并且是同一个对象
	 * @param object $object
	 * @param boolean $isStore 是否存储该对象
	 * @return object
	 */
	private static function parseObject($object, $isStore = true)
	{
		if(is_object($object))
		{
			return $object;
		}
		if(is_scalar($object))
		{
			if(isset(static::$objectMap[$object]))
			{
				return static::$objectMap[$object];
			}
			else if($isStore)
			{
				return static::$objectMap[$object] = (object)$object;
			}
			else
			{
				return (object)$object;
			}
		}
		else
		{
			// 其它
			if(false !== ($index = array_search($object, static::$otherMap, true)))
			{
				return static::$otherToObjectMap[$index];	
			}
			else if($isStore)
			{
				$key = spl_object_hash(new \stdclass);
				static::$otherMap[$key] = $object;
				return static::$otherToObjectMap[$key] = (object)$object;
			}
			else
			{
				return (object)$object;
			}
		}
	}
}
