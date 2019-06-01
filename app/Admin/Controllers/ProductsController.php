<?php

namespace App\Admin\Controllers;

use App\Models\Product;
use App\Http\Controllers\Controller;
use Encore\Admin\Controllers\HasResourceActions;
use Encore\Admin\Form;
use Encore\Admin\Grid;
use Encore\Admin\Layout\Content;
use Encore\Admin\Show;

class ProductsController extends Controller
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
            ->header('Product List')
            ->body($this->grid());
    }

    /**
     * Edit interface.
     *
     * @param mixed $id
     * @param Content $content
     * @return Content
     */
    public function edit($id, Content $content)
    {
        return $content
            ->header('Edit Product')
            ->body($this->form()->edit($id));
    }

    /**
     * Create interface.
     *
     * @param Content $content
     * @return Content
     */
    public function create(Content $content)
    {
        return $content
            ->header('Create Product')
            ->body($this->form());
    }

    /**
     * Make a grid builder.
     *
     * @return Grid
     */
    protected function grid()
    {
        $grid = new Grid(new Product);

        $grid->id('ID')->sortable();
        $grid->title('Product Name');
        $grid->on_sale('Sell')->display(function ($value) {
            return $value ? 'Y' : 'N';
        });
        $grid->price('Price');
        $grid->rating('Rate');
        $grid->sold_count('Sales Volume');
        $grid->review_count('Number of Comments ');

        $grid->actions(function ($actions) {
            $actions->disableView();
            $actions->disableDelete();
        });
        $grid->tools(function ($tools) {
            // disable batch remove
            $tools->batch(function ($batch) {
                $batch->disableDelete();
            });
        });

        return $grid;
    }

    /**
     * Make a form builder.
     *
     * @return Form
     */
    protected function form()
    {
        $form = new Form(new Product);

        // Create text
        $form->text('title', 'Product Name')->rules('required');

        //Create image
        $form->image('image', 'Image')->rules('required|image');

        // Create editor
        $form->editor('description', 'Product Description')->rules('required');

        // Create
        $form->radio('on_sale', 'Sell')->options(['1' => 'Y', '0'=> 'N'])->default('0');

        // 
        $form->hasMany('skus', 'SKU List', function (Form\NestedForm $form) {
            $form->text('title', 'SKU Name')->rules('required');
            $form->text('description', 'SKU Description')->rules('required');
            $form->text('price', 'Price')->rules('required|numeric|min:0.01');
            $form->text('stock', 'Stock')->rules('required|integer|min:0');
        });

        // Save
        $form->saving(function (Form $form) {
            $form->model()->price = collect($form->input('skus'))->where(Form::REMOVE_FLAG_NAME, 0)->min('price') ?: 0;
        });

        return $form;
    }
}
