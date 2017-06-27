# -*- coding: utf-8 -*-
# Generated by Django 1.9.1 on 2017-04-25 04:10
from __future__ import unicode_literals

from django.db import migrations, models
import django.db.models.deletion


class Migration(migrations.Migration):

    dependencies = [
        ('consulta', '0005_auto_20170425_0408'),
    ]

    operations = [
        migrations.RemoveField(
            model_name='triagem',
            name='id',
        ),
        migrations.AddField(
            model_name='triagem',
            name='tri_consulta',
            field=models.OneToOneField(default=1, on_delete=django.db.models.deletion.CASCADE, primary_key=True, serialize=False, to='consulta.Consulta'),
            preserve_default=False,
        ),
    ]
