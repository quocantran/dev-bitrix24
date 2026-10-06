<?php
if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED !== true) die();
?>
        </div><!-- /.page-container -->
    </main><!-- /.main-content-area -->

    <footer class="site-footer">
        <div class="footer-inner">
            <div class="footer-company-info">
                <?php
                $APPLICATION->IncludeComponent(
                    "bitrix:main.include",
                    "",
                    [
                        "AREA_FILE_SHOW" => "file",
                        "PATH" => SITE_DIR . "include/company_info.php"
                    ]
                );
                ?>
            </div>
            <div class="footer-copyright">
                <p>&copy; <?=date("Y")?> Hãng Kiểm toán AASC. Bản quyền thuộc về hệ sinh thái kiểm toán nội bộ.</p>
            </div>
        </div>
    </footer>
</body>
</html>
