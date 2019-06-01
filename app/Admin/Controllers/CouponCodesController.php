<?php

namespace App\Admin\Controllers;

use App\Models\CouponCode;
use App\Http\Controllers\Controller;
use Encore\Admin\Controllers\HasResourceActions;
use Encore\Admin\Form;
use Encore\Admin\Grid;
use Encore\Admin\Layout\Content;
use Encore\Admin\Show;

class CouponCodesController extends Controller
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
            ->header('Coupon Lists')
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
            ->header('Edit Coupon')
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
            ->header('New Coupon')
            ->body($this->form());
    }

    /**
     * Make a grid builder.
     *
     * @return Grid
     */
    protected function grid()
    {
        $grid = new Grid(new CouponCode);

        $grid->model()->orderBy('created_at', 'desc');
        $grid->id('ID')->sortable();
        $grid->name('Coupon');
        $grid->code('Coupon Code');
        $grid->description('Description');
        $grid->column('usage', 'Quantity')->display(function ($value) {
            return "{$this->used} / {$this->total}";
        });
        $grid->enabled('Use it now?')->display(function ($value) {
            return $value ? 'Y' : 'N';
        });
        $grid->created_at('Created Time');
        $grid->actions(function ($actions) {
            $actions->disableView();
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
        $form = new Form(new CouponCode);

        $form->display('id', 'ID');
        $form->text('name', 'Coupon')->rules('required');
        $form->text('code', 'Coupon Code')->rules(function($form) {
            // if $form->model()->id is valid，edit
            if ($id = $form->model()->id) {
                return 'nullable|unique:coupon_codes,code,'.$id.',id';
            } else {
                return 'nullable|unique:coupon_codes';
            }
        });
        $form->radio('type', 'Type')->options(CouponCode::$typeMap)->rules('required');
        $form->text('value', 'Discount')->rules(function ($form) {
            if ($form->model()->type === CouponCode::TYPE_PERCENT) {
                // Discount is between 1-99%
                return 'required|numeric|between:1,99';
            } else {
                // >0.1
                return 'required|numeric|min:0.01';
            }
        });
        $form->text('total', 'Total Quantity')->rules('required|numeric|min:0');
        $form->text('min_amount', 'Minimum Amount')->rules('required|numeric|min:0');
        $form->datetime('not_before', 'Start time');
        $form->datetime('not_after', 'End Time');
        $form->radio('enabled', 'Enable Now?')->options(['1' => 'Y', '0' => 'N']);

        $form->saving(function (Form $form) {
            if (!$form->code) {
                $form->code = CouponCode::findAvailableCode();
            }
        });

        return $form;
    }
}
