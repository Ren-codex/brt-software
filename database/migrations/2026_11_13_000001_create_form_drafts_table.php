<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A form someone started and set aside, kept so they can resume it later.
 *
 * This used to live only in the browser's localStorage, which field reps on
 * mobile browsers routinely have blocked or cleared — so a minimised order
 * vanished on the next refresh. Keeping it here, keyed to the user, makes it
 * survive a refresh, a new tab, or a different device.
 *
 * One draft per kind per user: a second sales order set aside replaces the
 * first, exactly as the single floating bar already implied.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('form_drafts', function (Blueprint $table) {
            $table->increments('id');

            $table->unsignedInteger('user_id');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');

            // Which form it is: 'sales-order', 'purchase-request'. Matches the
            // kinds declared in resources/js/Shared/State/modules/draft.js.
            $table->string('kind', 50);

            // A one-line label for the floating bar ("Dela Cruz · 3 items").
            $table->string('summary')->nullable();

            // The whole half-written form, as the client serialised it.
            $table->longText('payload')->nullable();

            // Where the form lives, so Resume returns to the right screen even
            // for an external rep whose orders are on a different route.
            $table->string('return_path')->nullable();

            $table->timestamp('saved_at')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'kind']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('form_drafts');
    }
};
