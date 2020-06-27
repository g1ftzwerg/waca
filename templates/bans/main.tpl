﻿{extends file="pagebase.tpl"}
{block name="content"}
    <div class="row">
        <div class="col-md-12" >
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2">Ban Management <small class="text-muted">View, ban, and unban requesters</small></h1>

                <div class="btn-toolbar mb-2 mb-md-0">
                    {if $canSet}
                        <a class="btn btn-sm btn-outline-success" href="{$baseurl}/internal.php/bans/set"><i class="fas fa-plus"></i>&nbsp;Add new Ban</a>
                    {/if}
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-md-12">
            <h3>Active Ban List</h3>
            {include file="bans/list.tpl"}
        </div>
    </div>
{/block}
