# CakePHP EAV Plugin

The EAV Plugin is based on the EAV Behavior which contains logic to implement the Entity, Attribute, Value design pattern in CakePHP.

The Entity-Attribute-Value Design Pattern is a strategy for dynamically adding fields to Models. The pattern is
often used to provide a solution for storing variable variable data. Some CRMs use this strategy in a SaaS
environment to allow customers to add their own fields.

Implementing an EAV pattern can be a complex difficult to manage process.This plugin makes provides a powerful
seamless solution. Attach the behavior to your models and in most cases, you can interact with your model as
though the dynamic fields were actually in your model's table. DefaultModel CRUD opertations work on the model
retrieving or saving Attribute Values.

Entities are the models you need to add Dynamic Fields to.
Attributes are the fields you need to add to an Entity.
Values are the are the field values stored for each Entity.


## Features

* Data is stored in 12 Attribute Value tables for 10 different data types and two different foreign key types.
* Simulates foreign key field with UUID and integer based virtual keys.
* Dynamic Models for Attribute Value tables.
* Supports 3 Virtual/Dynamic Fields including CakePHP's virtualFields.
* Globally Find and Replace content in text or string value models.
* Support multiple entities using UUIDs.
* Detaching and reattaching the Behavior can be used to provide different results sets depending on the type of query.

## Installation
* Clone/Copy the files in this directory into `app/Plugin/Eav`
* Ensure the plugin is loaded in `app/Config/bootstrap.php` by calling `CakePlugin::load('Eav');`
* Add the SQL to your database to add the plugin tables tables by running the sql script in `app/Config/Schema`.
* Attach the Eav Behavior to your Models. The behavior can be configured itself or when added to a model. `public $actsAs = array('Eav.Eav' => array('type' =>'entity'));`
* Navigate to domain.com/admin/eav/entity_types and add Entity Types for each model you intend to attach the EAV Behavior too. The name of the Entity Type should be the name of the model. (ex. User, Contact, Customer)
* Navigate to domain.com/admin/eav/attributes and add some attributes. You will need to associate the Attribute with an Entity and Data Type.

## Configuration

The Plugin has a few options that can be configured. If you are using Virtual Keys that are either integers or uuids they need to be added
like this.

    public $actsAs = array(
            'Eav.Eav' => array(
                    'type' =>'entity',
                    'virtualKeys' => array(  //Virtual Keys
                            'key' => array(
                                    'AssociatedModelWithIntId'
                            ),
                            'uuid' => array(
                                    'AssociatedModelWithUuidId'
                            )
                    ),
                    'attributeModel' => 'Attribute', // Change if you are using a different model for fields
                    'virtualFieldType' => 'cake' //Set this to one of the options listed below.
            )
    );
### VirtualFields
The EAV Behavior can use various forms of virtualFields
Options:
* cake - Use CakePHP's virtualFields
* eav - Simulates CakePHP's Virtual Fields without the sub queries
* array - Adds an array of attribute names and values to the find results
* false - Leave the Attribute Values in their respective arrays

## Dynamic Attribute Value Models
You may notice that there are 12 attribute value tables but no Attribute Value Models. The Eav Behavior utilizes CakePHP's dynamic models by default. In most cases this works extremely well as the associations often do not change.
There may be situations where this does not work for you. In those cases, you may need to create actual model files for each Attribute Value table.

## Containable Warning
Unfortunately, Cake Behaviors do not always play nice together. This is the case with Eav and the Containable behavior. Containable queries will not work with Eav models. The reason is that that the Containable Behavior will not call the callbacks from other Behaviors. The solution is to bind and unbind your modesls dynamically.
