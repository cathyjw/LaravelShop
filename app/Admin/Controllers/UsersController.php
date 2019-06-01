<?php

namespace App\Admin\Controllers;

use App\Models\User;
use App\Http\Controllers\Controller;
use Encore\Admin\Controllers\HasResourceActions;
use Encore\Admin\Form;
use Encore\Admin\Grid;
use Encore\Admin\Layout\Content;
use Encore\Admin\Show;

class UsersController extends Controller
{
    use HasResourceActions;

    /**
     * Index interface.
     *
     * @param Content $content
     * @return Content
     */
    public function index(Content $content)
    {
        return $content
            ->header('User List')
            ->body($this->grid());
    }

    /**
     * Make a grid builder.
     *
     * @return Grid
     */
    protected function grid()
    {
        $grid = new Grid(new User);

        // 
        $grid->id('ID');

        // 
        $grid->name('Username');

        $grid->email('Email');

        $grid->email_verified_at('Verified Email')->display(function ($value) {
            return $value ? 'Y' : 'N';
        });

        $grid->created_at('Registration Date');

        // Disable create button, can not create in backstage
        $grid->disableCreateButton();

        $grid->actions(function ($actions) {
            // 
            $actions->disableView();
            // 
            $actions->disableDelete();
            // 
            $actions->disableEdit();
        });

        $grid->tools(function ($tools) {
            // 
            $tools->batch(function ($batch) {
                $batch->disableDelete();
            });
        });

        return $grid;
    }
}
