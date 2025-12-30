<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateProductsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('products', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->increments('id');
            $table->integer('pack_size');
            $table->unsignedInteger('unit_id');
            $table->string('price', 20)->default('0.00');
            $table->foreign('unit_id')->references('id')->on('list_units')->onDelete('cascade');
            $table->boolean('is_active')->default('1');
            $table->unsignedInteger('brand_id');
            $table->foreign('brand_id')->references('id')->on('list_brands')->onDelete('cascade');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('products');
    }
}
