CREATE TABLE "orders" (
    "id" SERIAL, 
    "name" varchar(32) null DEFAULT null, 
    "description" varchar(256) null DEFAULT null,
    "price" int null DEFAULT null,
    "customer_id" int not null,
    "executor_id" int null DEFAULT null,
    "post_id" int null DEFAULT null);

