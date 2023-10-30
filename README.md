## MySQL DB Proxy

A need to access webhosting database remotely has arised. However that is forbidden by default, security reasons. So I had to look for a _much more secure solution_.

This is a rework of a mysql_proxy I found on Source Forge, will link it back once I find it again.

Response:
```
{
    "query": "select current_timestamp;",
    "error_number": 0,
    "error_desc": "",
    "affected_rows": 1,
    "insert_id": 0,
    "field_count": 1,
    "rows": [
        {
            "current_timestamp": "2020-05-31 16:19:08"
        }
    ]
}
```
