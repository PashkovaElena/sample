<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateMarketsTable extends Migration
{

    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('markets', function (Blueprint $table) {
            $table->increments('id');
            $table->string('name');
	    $table->integer('languages_id')->unsigned()->default(1);
            $table->timestamps();

            $table->foreign('languages_id')->references('id')->on('languages')
                ->onUpdate('cascade')
                ->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('markets', function (Blueprint $table) {
            $table->dropForeign('markets_languages_id_foreign');
            $table->dropColumn('languages_id');
        });

        Schema::drop('markets');
    }

}
