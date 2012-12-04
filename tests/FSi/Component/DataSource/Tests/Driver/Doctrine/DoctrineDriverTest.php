<?php

/*
 * This file is part of the FSi Component package.
 *
 * (c) Szczepan Cieslik <szczepan@fsi.pl>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace FSi\Component\DataSource\Tests\Driver\Doctrine;

use Doctrine\ORM\Tools\Setup;
use Doctrine\ORM\EntityManager;
use FSi\Component\DataSource\DataSourceInterface;
use FSi\Component\DataSource\Tests\Fixtures\News;
use FSi\Component\DataSource\Tests\Fixtures\Category;
use FSi\Component\DataSource\Tests\Fixtures\Group;
use FSi\Component\DataSource\Tests\Fixtures\TestManagerRegistry;
use FSi\Component\DataSource\Driver\Doctrine\Extension\Core\CoreExtension;
use FSi\Component\DataSource\Driver\Doctrine\DoctrineFactory;
use FSi\Component\DataSource\Extension\Symfony\Form\FormExtension;
use FSi\Component\DataSource\Extension\Symfony;
use FSi\Component\DataSource\Extension\Core;
use FSi\Component\DataSource\Extension\Core\Ordering\OrderingExtension;
use Symfony\Component\Form;
use Symfony\Bridge\Doctrine\Form\DoctrineOrmExtension;

/**
 * Tests for Doctrine driver.
 */
class DoctrineDriverTest extends \PHPUnit_Framework_TestCase
{
    /**
     * {@inheritdoc}
     */
    public function setUp()
    {
        if (!class_exists('Doctrine\ORM\EntityManager')) {
            $this->markTestSkipped('Doctrine needed!');
        }

        if (!class_exists('Symfony\Component\Form\Form')) {
            $this->markTestSkipped('Symfony Form needed!');
        }

        // the connection configuration
        $dbParams = array(
            'driver' => 'pdo_sqlite',
            'memory' => true,
        );

        $config = Setup::createAnnotationMetadataConfiguration(array(FIXTURES_PATH), true, null, null, false);
        $em = EntityManager::create($dbParams, $config);
        $tool = new \Doctrine\ORM\Tools\SchemaTool($em);
        $classes = array(
            $em->getClassMetadata('FSi\Component\DataSource\Tests\Fixtures\News'),
            $em->getClassMetadata('FSi\Component\DataSource\Tests\Fixtures\Category'),
            $em->getClassMetadata('FSi\Component\DataSource\Tests\Fixtures\Group'),
        );
        $tool->createSchema($classes);
        $this->load($em);
        $this->em = $em;
    }

    /**
     * General test for DataSource wtih DoctrineDriver in basic configuration.
     */
    public function testGeneral()
    {
        $driverFactory = $this->getDoctrineFactory();
        $dataSourceFactory = $this->getDataSourceFactory();
        $datasources = array();

        $driver = $driverFactory->createDriver('FSi\Component\DataSource\Tests\Fixtures\News');
        $datasources[] = $dataSourceFactory->createDataSource($driver, 'datasource');

        $qb = $this->em
            ->createQueryBuilder()
            ->select('n')
            ->from('FSi\Component\DataSource\Tests\Fixtures\News', 'n')
        ;
        $driver = $driverFactory->createDriver($qb, 'n');
        $datasources[] = $dataSourceFactory->createDataSource($driver, 'datasource2');

        foreach ($datasources as $datasource) {
            $datasource
                ->addField('title', 'text', 'like')
                ->addField('author', 'text', 'like')
                ->addField('time', 'time', 'between', array(
                    'field_mapping' => 'create_time',
                ))
                ->addField('category', 'entity', 'eq', array(
                    'form_options' => array(
                        'class' => 'FSi\Component\DataSource\Tests\Fixtures\Category',
                    ),
                ))
                ->addField('group', 'entity', 'memberof', array(
                    'field_mapping' => 'groups',
                    'form_options' => array(
                        'class' => 'FSi\Component\DataSource\Tests\Fixtures\Group',
                    ),
                ))
            ;

            $result1 = $datasource->getResult();
            $this->assertEquals(100, count($result1));
            $view1 = $datasource->createView();

            //Checking if result cache works.
            $this->assertSame($result1, $datasource->getResult());

            $parameters = array(
                $datasource->getName() => array(
                    DataSourceInterface::FIELDS => array(
                        'author' => 'domain1.com',
                    ),
                ),
            );
            $datasource->bindParameters($parameters);
            $result2 = $datasource->getResult();

            //Checking cache.
            $this->assertSame($result2, $datasource->getResult());

            $this->assertEquals(50, count($result2));
            $this->assertNotSame($result1, $result2);
            unset($result1);
            unset($result2);

            $this->assertEquals($parameters, $datasource->getParameters());

            $datasource->setMaxResults(20);
            $parameters = array(
                $datasource->getName() => array(
                    DataSourceInterface::PAGE => 1,
                ),
            );

            $datasource->bindParameters($parameters);
            $result = $datasource->getResult();
            $this->assertEquals(100, count($result));
            $i = 0;
            foreach ($result as $item) {
                $i++;
            }

            $this->assertEquals(20, $i);

            $parameters = array(
                $datasource->getName() => array(
                    DataSourceInterface::FIELDS => array(
                        'author' => 'domain1.com',
                        'title' => 'title3',
                    ),
                ),
            );

            $datasource->bindParameters($parameters);
            $view = $datasource->createView();
            $result = $datasource->getResult();
            $this->assertEquals(5, count($result));

            //Checking entity fields. We assume that database was created so first category and first group have ids equal to 1.
            $parameters = array(
                $datasource->getName() => array(
                    DataSourceInterface::FIELDS => array(
                        'group' => 1,
                    ),
                ),
            );

            $datasource->bindParameters($parameters);
            $result = $datasource->getResult();
            $this->assertEquals(25, count($result));

            $parameters = array(
                $datasource->getName() => array(
                    DataSourceInterface::FIELDS => array(
                        'category' => 1,
                    ),
                ),
            );

            $datasource->bindParameters($parameters);
            $result = $datasource->getResult();
            $this->assertEquals(20, count($result));

            $parameters = array(
                $datasource->getName() => array(
                    DataSourceInterface::FIELDS => array(
                        'group' => 1,
                        'category' => 1,
                    ),
                ),
            );

            $datasource->bindParameters($parameters);
            $result = $datasource->getResult();
            $this->assertEquals(5, count($result));

            //Checking sorting.
            $parameters = array(
                $datasource->getName() => array(
                    OrderingExtension::ORDERING => array(
                        'title' => array(
                            OrderingExtension::ORDERING_OPTION => 'asc',
                            OrderingExtension::ORDERING_PRIORITY_OPTION => 1,
                        ),
                    ),
                ),
            );

            $datasource->bindParameters($parameters);
            foreach ($datasource->getResult() as $news) {
                $this->assertEquals('title0', $news->getTitle());
                break;
            }

            //Checking sorting.
            $parameters = array(
                $datasource->getName() => array(
                    OrderingExtension::ORDERING => array(
                        'title' => array(
                            OrderingExtension::ORDERING_OPTION => 'desc',
                            OrderingExtension::ORDERING_PRIORITY_OPTION => 2,
                        ),
                        'author' => array(
                            OrderingExtension::ORDERING_OPTION => 'asc',
                            OrderingExtension::ORDERING_PRIORITY_OPTION => 1,
                        ),
                    ),
                ),
            );

            $datasource->bindParameters($parameters);
            foreach ($datasource->getResult() as $news) {
                $this->assertEquals('title99', $news->getTitle());
                break;
            }

            //Test for clearing fields.
            $datasource->clearFields();
            $parameters = array(
                $datasource->getName() => array(
                    DataSourceInterface::FIELDS => array(
                        'author' => 'domain1.com',
                    ),
                ),
            );

            //Since there are no fields now, we should have all of entities.
            $datasource->bindParameters($parameters);
            $result = $datasource->getResult();
            $this->assertEquals(100, count($result));
        }
    }

    /**
     * Checks DataSource wtih DoctrineDriver using more sophisticated QueryBuilder.
     */
    public function testQuery()
    {
        $driverFactory = $this->getDoctrineFactory();
        $dataSourceFactory = $this->getDataSourceFactory();

        $qb = $this->em
            ->createQueryBuilder()
            ->select('n')
            ->from('FSi\Component\DataSource\Tests\Fixtures\News', 'n')
            ->join('n.category', 'c')
            ->join('n.groups', 'g')
        ;
        $driver = $driverFactory->createDriver($qb, 'n');
        $datasource = $dataSourceFactory->createDataSource($driver);

        $datasource
            ->addField('author', 'text', 'like')
            ->addField('category', 'text', 'like', array(
                'field_mapping' => 'c.name',
            ))
            ->addField('group', 'text', 'like', array(
                'field_mapping' => 'g.name',
            ))
        ;

        $parameters = array(
            $datasource->getName() => array(
                DataSourceInterface::FIELDS => array(
                    'group' => 'group0',
                ),
            ),
        );

        $datasource->bindParameters($parameters);
        $this->assertEquals(25, count($datasource->getResult()));

        $parameters = array(
            $datasource->getName() => array(
                DataSourceInterface::FIELDS => array(
                    'group' => 'group',
                ),
            ),
        );

        $datasource->bindParameters($parameters);
        $this->assertEquals(100, count($datasource->getResult()));

        $parameters = array(
            $datasource->getName() => array(
                DataSourceInterface::FIELDS => array(
                    'group' => 'group0',
                    'category' => 'category0',
                ),
            ),
        );

        $datasource->bindParameters($parameters);
        $this->assertEquals(5, count($datasource->getResult()));
    }

    /**
     * {@inheritdoc}
     */
    public function tearDown()
    {
        unset($this->em);
    }

    /**
     * Returns mock of FormFactory.
     *
     * @return object
     */
    private function getFormFactory()
    {
        $typeFactory = new Form\ResolvedFormTypeFactory();
        $registry = new Form\FormRegistry(
            array(
                new Form\Extension\Core\CoreExtension(),
                new Form\Extension\Csrf\CsrfExtension(
                    new Form\Extension\Csrf\CsrfProvider\DefaultCsrfProvider('secret')
                ),
                new DoctrineOrmExtension(new TestManagerRegistry($this->em)),
            ),
            $typeFactory
        );
        return new Form\FormFactory($registry, $typeFactory);
    }

    private function getDoctrineFactory()
    {
        $extensions = array(
            new CoreExtension(),
        );

        return new DoctrineFactory($this->em, $extensions);
    }

    private function getDataSourceFactory()
    {
        $extensions = array(
            new Symfony\CoreExtension(),
            new FormExtension($this->getFormFactory()),
            new Core\Pagination\PaginationExtension(),
            new OrderingExtension(),
        );
        return new \FSi\Component\DataSource\DataSourceFactory($extensions);
    }

    private function load($em)
    {
        //Injects 5 categories.
        $categories = array();
        for ($i = 0; $i < 5; $i++) {
            $category = new Category();
            $category->setName('category'.$i);
            $em->persist($category);
            $categories[] = $category;
        }

        //Injects 4 groups.
        $groups = array();
        for ($i = 0; $i < 4; $i++) {
            $group = new Group();
            $group->setName('group'.$i);
            $em->persist($group);
            $groups[] = $group;
        }

        //Injects 100 newses.
        for ($i = 0; $i < 100; $i++) {
            $news = new News();
            $news->setTitle('title'.$i);

            //Half of entities will have different author and content.
            if ($i % 2 == 0) {
                $news->setAuthor('author'.$i.'@domain1.com');
                $news->setShortContent('Lorem ipsum.');
                $news->setContent('Content lorem ipsum.');
            } else {
                $news->setAuthor('author'.$i.'@domain2.com');
                $news->setShortContent('Dolor sit amet.');
                $news->setContent('Content dolor sit amet.');
            }

            //Each entity has different date of creation and one of four hours of creation.
            $createDate = new \DateTime(date("Y:m:d H:i:s", $i * 24 * 60 * 60));
            $createTime = new \DateTime(date("H:i:s", (($i % 4) + 1 ) * 60 * 60));

            $news->setCreateDate($createDate);
            $news->setCreateTime($createTime);

            $news->setCategory($categories[$i % 5]);
            $news->getGroups()->add($groups[$i % 4]);

            $em->persist($news);
        }

        $em->flush();
    }
}