// Settings — ports api/save_settings.php and classes/Setting.php

(() => {

    const ALLOWED = [
        'shop_name', 'shop_address', 'shop_phone',
        'shop_email', 'currency', 'invoice_prefix',
    ];

    ApiRouter.register({

        'save_settings.php': ApiRouter.adminOnly((p) => {
            let saved = 0;

            ALLOWED.forEach(key => {
                if (Object.prototype.hasOwnProperty.call(p, key)) {
                    DemoDB.setSetting(key, String(p[key]).trim());
                    saved++;
                }
            });

            if (saved === 0) return ApiRouter.fail('সংরক্ষণ করার মতো কিছু নেই।');

            DemoDB.log('update_settings', 'settings', 0, 'Shop settings updated');
            return ApiRouter.ok('সেটিংস সংরক্ষণ করা হয়েছে।');
        }),
    });
})();
