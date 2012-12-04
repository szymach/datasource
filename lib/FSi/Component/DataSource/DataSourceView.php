<?php

/*
 * This file is part of the FSi Component package.
 *
 * (c) Szczepan Cieslik <szczepan@fsi.pl>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace FSi\Component\DataSource;

use FSi\Component\DataSource\Exception\DataSourceViewException;

/**
 * {@inheritdoc}
 */
class DataSourceView implements DataSourceViewInterface
{
    /**
     * @var DataSource
     */
    private $datasource;

    /**
     * Array of field views.
     *
     * @var array
     */
    private $fields = array();

    /**
     * Options of view.
     *
     * @var array
     */
    private $options = array();

    /**
     * Fields iterator.
     *
     * @var \ArrayIterator
     */
    private $iterator;

    /**
     * Constructor.
     *
     * @param DataSource $datasource
     */
    public function __construct(DataSource $datasource)
    {
        $this->datasource = $datasource;
    }

    /**
     * {@inheritdoc}
     */
    public function getParameters()
    {
        return $this->datasource->getParameters();
    }

    /**
     * {@inheritdoc}
     */
    public function getAllParameters()
    {
        return $this->datasource->getAllParameters();
    }

    /**
     * {@inheritdoc}
     */
    public function getOtherParameters()
    {
        return $this->datasource->getOtherParameters();
    }

    /**
     * {@inheritdoc}
     */
    public function hasOption($name)
    {
        return isset($this->options[$name]);
    }

    /**
     * {@inheritdoc}
     */
    public function setOption($name, $value)
    {
        $this->options[$name] = $value;

        //Case when i.e. null was given as $value is problematic,
        //because then you can getOption with that name, but hasOption will return false,
        //also that key would appear in array from getOptions method.
        if (!isset($this->options[$name])) {
            unset($this->options[$name]);
        }
    }

    /**
     * {@inheritdoc}
     *
     * @throws DataSourceViewException
     */
    public function getOption($name)
    {
        if (!$this->hasOption($name)) {
            throw new DataSourceViewException(sprintf('There\'s no option with name "%s"', $name));
        }
        return $this->options[$name];
    }

    /**
     * {@inheritdoc}
     */
    public function getOptions()
    {
        return $this->options;
    }

    /**
     * {@inheritdoc}
     */
    public function removeOption($name)
    {
        if (isset($this->options[$name])) {
            unset($this->options[$name]);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function hasField($name)
    {
        return isset($this->fields[$name]);
    }

    /**
     * {@inheritdoc}
     */
    public function getField($name)
    {
        if (!$this->hasField($name)) {
            throw new DataSourceViewException(sprintf('There\'s no field with name "%s"', $name));
        }
        return $this->fields[$name];
    }

    /**
     * {@inheritdoc}
     */
    public function getFields()
    {
        return $this->fields;
    }

    /**
     * {@inheritdoc}
     */
    public function addField(Field\FieldViewInterface $fieldView)
    {
        $name = $fieldView->getField()->getName();
        if ($this->hasField($name)) {
            throw new DataSourceViewException(sprintf('There\'s already field with name "%s"', $name));
        }
        $this->fields[$name] = $fieldView;
        $fieldView->setDataSourceView($this);
        $this->iterator = null;
    }

    /**
     * Method to fetch result from datasource.
     *
     * @return mixed
     */
    private function getResult()
    {
        return $this->datasource->getResult();
    }

    /**
     * Implementation of \ArrayAccess interface method.
     *
     * @param mixed $offset
     * @return bool
     */
    public function offsetExists($offset)
    {
        return isset($this->fields[$offset]);
    }

    /**
     * Implementation of \ArrayAccess interface method.
     *
     * @param mixed $offset
     * @return mixed
     */
    public function offsetGet($offset)
    {
        return $this->fields[$offset];
    }

    /**
     * Implementation of \ArrayAccess interface method.
     *
     * In fact it does nothing - view shouldn't set its fields in this way.
     *
     * @param mixed $offset
     * @param mixed $value
     */
    public function offsetSet($offset, $value)
    {
        return;
    }

    /**
     * Implementation of \ArrayAccess interface method.
     *
     * In fact it does nothing - view shouldn't unset its fields in this way.
     *
     * @param mixed $offset
     */
    public function offsetUnset($offset)
    {
        return;
    }

    /**
     * Implementation of \Countable interface method.
     *
     * @return integer
     */
    public function count()
    {
        return count($this->fields);
    }

    /**
     * Implementation of \SeekableIterator interface method.
     *
     * @param integer $position
     */
    public function seek($position)
    {
        $this->checkIterator();
        return $this->iterator->seek($position);
    }

    /**
     * Implementation of \SeekableIterator interface method.
     *
     * @return mixed
     */
    public function current()
    {
        $this->checkIterator();
        return $this->iterator->current();
    }

    /**
     * Implementation of \SeekableIterator interface method.
     *
     * @return mixed
     */
    public function key()
    {
        $this->checkIterator();
        return $this->iterator->key();
    }

    /**
     * Implementation of \SeekableIterator interface method.
     */
    public function next()
    {
        $this->checkIterator();
        return $this->iterator->next();
    }

    /**
     * Implementation of \SeekableIterator interface method.
     */
    public function rewind()
    {
        $this->checkIterator();
        return $this->iterator->rewind();
    }

    /**
     * Implementation of \SeekableIterator interface method.
     *
     * @return bool
     */
    public function valid()
    {
        $this->checkIterator();
        return $this->iterator->valid();
    }

    /**
     * Inits iterator.
     */
    private function checkIterator()
    {
        if (!isset($this->iterator)) {
            $this->iterator = new \ArrayIterator($this->fields);
        }
    }
}